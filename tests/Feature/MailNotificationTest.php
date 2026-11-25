<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use SalvatoreCervone\BackupDatabase\BackupDatabase;
use SalvatoreCervone\BackupDatabase\Jobs\SendBackupNotificationJob;
use SalvatoreCervone\BackupDatabase\Mail\BackupFailedMail;
use SalvatoreCervone\BackupDatabase\Mail\BackupSuccessMail;
use SalvatoreCervone\BackupDatabase\Mail\BackupSummaryMail;
use SalvatoreCervone\BackupDatabase\Mail\TestNotificationMail;
use SalvatoreCervone\BackupDatabase\Support\BackupNotifier;
use SalvatoreCervone\BackupDatabase\Support\MailSettingsManager;
use SalvatoreCervone\BackupDatabase\Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->settingsFile = sys_get_temp_dir().'/backup-db-mail-settings-'.uniqid().'.json';
    config(['backup-database.notifications.settings_path' => $this->settingsFile]);
    config(['backup-database.notification_email' => null]);
    config(['backup-database.notifications.on_success' => false]);
    config(['backup-database.notifications.on_failure' => true]);
    config(['backup-database.notifications.summary' => false]);

    // Configure a working default .env mail setup for tests
    config(['queue.default' => 'sync']);
    config(['mail.default' => 'smtp']);
    config(['mail.mailers.smtp.host' => 'smtp.env.test']);
    config(['mail.mailers.smtp.port' => 587]);
    config(['mail.mailers.smtp.username' => 'env-user']);
    config(['mail.mailers.smtp.password' => 'env-secret']);
    config(['mail.mailers.smtp.encryption' => 'tls']);
    config(['mail.from.address' => 'noreply@env.test']);
    config(['mail.from.name' => 'Env App']);
});

afterEach(function () {
    if (isset($this->settingsFile) && File::exists($this->settingsFile)) {
        File::delete($this->settingsFile);
    }
});

// =============================================================================
// RECIPIENT PARSING & FALLBACK
// =============================================================================

it('parses multiple recipients separated by comma, semicolon, or newline', function () {
    $parsed = MailSettingsManager::parseRecipients("admin@example.com, ops@example.com;\n backup@example.com \n admin@example.com, invalid-email");

    expect($parsed)->toBe([
        'admin@example.com',
        'ops@example.com',
        'backup@example.com',
    ]);
});

it('falls back to config notification_email when panel recipients are empty', function () {
    config(['backup-database.notification_email' => 'env1@example.com, env2@example.com']);

    $manager = new MailSettingsManager;

    expect($manager->getRecipients())->toBe(['env1@example.com', 'env2@example.com']);
});

// =============================================================================
// SMTP FALLBACK TO .ENV & PANEL OVERRIDES
// =============================================================================

it('uses .env mail configuration when panel SMTP fields are empty', function () {
    $manager = new MailSettingsManager;

    $effective = $manager->getEffectiveSmtpConfig();

    expect($manager->hasCustomSmtpSettings())->toBeFalse()
        ->and($effective['source'])->toBe('env')
        ->and($effective['is_configured'])->toBeTrue()
        ->and($effective['host'])->toBe('smtp.env.test')
        ->and($effective['port'])->toBe(587)
        ->and($effective['username'])->toBe('env-user')
        ->and($effective['password'])->toBe('env-secret')
        ->and($effective['encryption'])->toBe('tls')
        ->and($effective['from_address'])->toBe('noreply@env.test')
        ->and($effective['from_name'])->toBe('Env App');
});

it('reports unconfigured when both panel and .env SMTP host are empty', function () {
    config(['mail.default' => 'smtp']);
    config(['mail.mailers.smtp.host' => null]);

    $manager = new MailSettingsManager;
    $effective = $manager->getEffectiveSmtpConfig();

    expect($effective['source'])->toBe('unconfigured')
        ->and($effective['is_configured'])->toBeFalse();
});

it('overrides SMTP fields from panel, encrypts password at rest, and never leaks password to UI', function () {
    $manager = new MailSettingsManager;

    $ui = $manager->save([
        'notify_on_success' => true,
        'notify_on_failure' => true,
        'notify_summary' => true,
        'recipients' => 'team@example.com',
        'smtp_host' => 'smtp.custom.test',
        'smtp_port' => 465,
        'smtp_username' => 'custom-user',
        'smtp_password' => 'super-secret-pass',
        'smtp_encryption' => 'ssl',
        'from_address' => 'backup@custom.test',
        'from_name' => 'Custom Backup',
    ]);

    // Verify raw file does NOT store plaintext password
    $rawContents = File::get($this->settingsFile);
    expect($rawContents)->not->toContain('super-secret-pass')
        ->and($rawContents)->toContain('smtp_password_encrypted');

    // Verify UI payload hides password
    expect($ui)->not->toHaveKey('smtp_password')
        ->and($ui['has_smtp_password'])->toBeTrue()
        ->and($ui['smtp_source'])->toBe('panel');

    // Verify effective config decrypts and uses panel values
    $effective = $manager->getEffectiveSmtpConfig();
    expect($effective['source'])->toBe('panel')
        ->and($effective['host'])->toBe('smtp.custom.test')
        ->and($effective['port'])->toBe(465)
        ->and($effective['username'])->toBe('custom-user')
        ->and($effective['password'])->toBe('super-secret-pass')
        ->and($effective['encryption'])->toBe('ssl')
        ->and($effective['from_address'])->toBe('backup@custom.test');

    // Saving again without smtp_password preserves existing password
    $manager->save([
        'notify_on_success' => true,
        'notify_on_failure' => false,
        'notify_summary' => false,
        'recipients' => 'team@example.com',
        'smtp_host' => 'smtp.custom.test',
        'smtp_password' => '',
    ]);
    expect($manager->get()['smtp_password'])->toBe('super-secret-pass');

    // Clearing password falls back to .env password
    $manager->save([
        'notify_on_success' => true,
        'notify_on_failure' => false,
        'notify_summary' => false,
        'recipients' => 'team@example.com',
        'smtp_host' => 'smtp.custom.test',
        'clear_smtp_password' => true,
    ]);
    expect($manager->get()['smtp_password'])->toBeNull()
        ->and($manager->getEffectiveSmtpConfig()['password'])->toBe('env-secret');
});

// =============================================================================
// NOTIFICATION FLAGS DURING BACKUP EXECUTION
// =============================================================================

it('sends only BackupSuccessMail when only notify_on_success is enabled and backup succeeds', function () {
    Mail::fake();
    Process::fake();

    $manager = new MailSettingsManager;
    $manager->save([
        'notify_on_success' => true,
        'notify_on_failure' => false,
        'notify_summary' => false,
        'recipients' => 'ok@example.com, ops@example.com',
    ]);

    config(['backup-database.listconnections' => [
        [
            'connection' => 'mysql',
            'destinationpath' => '/tmp/',
            'db_name' => 'test_db',
            'daily' => false,
        ],
    ]]);
    config(['database.connections.mysql' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'database' => 'test_db',
        'username' => 'root',
        'password' => 'password',
    ]]);

    $service = new BackupDatabase;
    $service->backup();

    Mail::assertSent(BackupSuccessMail::class, function (BackupSuccessMail $mail) {
        return $mail->hasTo('ok@example.com')
            && $mail->hasTo('ops@example.com')
            && count($mail->backups) === 1;
    });
    Mail::assertNotSent(BackupFailedMail::class);
    Mail::assertNotSent(BackupSummaryMail::class);
});

it('sends BackupFailedMail and NOT BackupSuccessMail when backup fails', function () {
    Mail::fake();
    Process::fake([
        '*' => Process::result(output: '', errorOutput: 'Access denied', exitCode: 1),
    ]);

    $manager = new MailSettingsManager;
    $manager->save([
        'notify_on_success' => true,
        'notify_on_failure' => true,
        'notify_summary' => false,
        'recipients' => 'alerts@example.com',
    ]);

    config(['backup-database.listconnections' => [
        [
            'connection' => 'mysql',
            'destinationpath' => '/tmp/',
            'db_name' => 'test_db',
            'daily' => false,
        ],
    ]]);
    config(['database.connections.mysql' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'database' => 'test_db',
        'username' => 'root',
        'password' => 'password',
    ]]);

    $service = new BackupDatabase;
    $service->backup();

    Mail::assertSent(BackupFailedMail::class, function (BackupFailedMail $mail) {
        return $mail->hasTo('alerts@example.com')
            && $mail->connectionName === 'mysql';
    });
    Mail::assertNotSent(BackupSuccessMail::class);
    Mail::assertNotSent(BackupSummaryMail::class);
});

it('sends BackupSummaryMail on both success and failure when notify_summary is enabled', function () {
    Mail::fake();
    Process::fake();

    $manager = new MailSettingsManager;
    $manager->save([
        'notify_on_success' => false,
        'notify_on_failure' => false,
        'notify_summary' => true,
        'recipients' => 'summary@example.com',
    ]);

    config(['backup-database.listconnections' => [
        [
            'connection' => 'mysql',
            'destinationpath' => '/tmp/',
            'db_name' => 'test_db',
            'daily' => false,
        ],
    ]]);
    config(['database.connections.mysql' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'database' => 'test_db',
        'username' => 'root',
        'password' => 'password',
    ]]);

    $service = new BackupDatabase;
    $service->backup();

    Mail::assertSent(BackupSummaryMail::class, function (BackupSummaryMail $mail) {
        return $mail->hasTo('summary@example.com')
            && count($mail->connectionResults) === 1
            && $mail->connectionResults[0]['status'] === true;
    });
    Mail::assertNotSent(BackupSuccessMail::class);
    Mail::assertNotSent(BackupFailedMail::class);
});

it('sends no emails when all flags are disabled', function () {
    Mail::fake();
    Process::fake();

    $manager = new MailSettingsManager;
    $manager->save([
        'notify_on_success' => false,
        'notify_on_failure' => false,
        'notify_summary' => false,
        'recipients' => 'nobody@example.com',
    ]);

    config(['backup-database.listconnections' => [
        [
            'connection' => 'mysql',
            'destinationpath' => '/tmp/',
            'db_name' => 'test_db',
            'daily' => false,
        ],
    ]]);
    config(['database.connections.mysql' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'database' => 'test_db',
        'username' => 'root',
        'password' => 'password',
    ]]);

    (new BackupDatabase)->backup();

    Mail::assertNothingSent();
});

it('configures dynamic backup_database_smtp mailer when custom SMTP is set', function () {
    Mail::fake();

    $manager = new MailSettingsManager;
    $manager->save([
        'notify_on_success' => true,
        'notify_on_failure' => false,
        'notify_summary' => false,
        'recipients' => 'custom@example.com',
        'smtp_host' => 'smtp.override.test',
        'smtp_port' => 2525,
        'smtp_username' => 'override-user',
        'smtp_password' => 'override-pass',
        'smtp_encryption' => 'tls',
    ]);

    $notifier = new BackupNotifier($manager);
    $result = $notifier->sendTest();

    expect($result['status'])->toBeTrue()
        ->and($result['smtp_source'])->toBe('panel')
        ->and(config('mail.mailers.backup_database_smtp.host'))->toBe('smtp.override.test')
        ->and(config('mail.mailers.backup_database_smtp.port'))->toBe(2525)
        ->and(config('mail.mailers.backup_database_smtp.username'))->toBe('override-user')
        ->and(config('mail.mailers.backup_database_smtp.password'))->toBe('override-pass');

    Mail::assertSent(TestNotificationMail::class, fn ($mail) => $mail->hasTo('custom@example.com'));
});

// =============================================================================
// HTTP ENDPOINTS & SECURITY
// =============================================================================

it('protects mail settings routes for unauthenticated users', function () {
    expect($this->getJson(route('backup.settings.mail.get'))->status())->toBeIn([401, 403]);
    expect($this->postJson(route('backup.settings.mail.save'), [])->status())->toBeIn([401, 403]);
    expect($this->postJson(route('backup.settings.mail.test'), [])->status())->toBeIn([401, 403]);
});

it('allows authenticated users to save settings, validates invalid emails, and sends test mail', function () {
    Mail::fake();

    $user = new class extends User
    {
        protected $guarded = [];
    };
    $user->id = 1;

    // Invalid recipient email should fail validation (422)
    $this->actingAs($user)
        ->postJson(route('backup.settings.mail.save'), [
            'notify_on_success' => true,
            'recipients' => 'valid@example.com, not-an-email',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipients']);

    // Valid save should succeed
    $this->actingAs($user)
        ->postJson(route('backup.settings.mail.save'), [
            'notify_on_success' => true,
            'notify_on_failure' => true,
            'notify_summary' => true,
            'recipients' => 'admin@example.com, ops@example.com',
            'smtp_host' => '',
        ])
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('settings.notify_on_success', true)
        ->assertJsonPath('settings.notify_summary', true)
        ->assertJsonPath('settings.smtp_source', 'env');

    // GET settings returns persisted values
    $this->actingAs($user)
        ->getJson(route('backup.settings.mail.get'))
        ->assertOk()
        ->assertJsonPath('recipients', 'admin@example.com, ops@example.com')
        ->assertJsonPath('smtp_source', 'env');

    // Test email endpoint sends TestNotificationMail
    $this->actingAs($user)
        ->postJson(route('backup.settings.mail.test'))
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('smtp_source', 'env');

    Mail::assertSent(TestNotificationMail::class, function (TestNotificationMail $mail) {
        return $mail->hasTo('admin@example.com') && $mail->hasTo('ops@example.com');
    });
});

it('renders the dashboard view with the email and SMTP settings panel', function () {
    $user = new class extends User
    {
        protected $guarded = [];
    };
    $user->id = 1;

    config(['backup-database.listconnections' => []]);

    $this->actingAs($user)
        ->get(route('backups.index'))
        ->assertOk()
        ->assertSee('Notifiche Email')
        ->assertSee('Email quando va tutto bene')
        ->assertSee('Email quando va male')
        ->assertSee('Email riepilogativa')
        ->assertSee('Impostazioni SMTP')
        ->assertSee('SMTP: .env');
});

it('dispatches backup notification emails to the Laravel queue via SendBackupNotificationJob', function () {
    Queue::fake();
    Process::fake();

    config(['backup-database.notifications.use_queue' => true]);
    config(['backup-database.notifications.queue_name' => 'emails']);

    $manager = new MailSettingsManager;
    $manager->save([
        'notify_on_success' => true,
        'notify_on_failure' => false,
        'notify_summary' => true,
        'recipients' => 'queued@example.com',
    ]);

    config(['backup-database.listconnections' => [
        [
            'connection' => 'mysql',
            'destinationpath' => '/tmp/',
            'db_name' => 'test_db',
            'daily' => false,
        ],
    ]]);
    config(['database.connections.mysql' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'database' => 'test_db',
        'username' => 'root',
        'password' => 'password',
    ]]);

    (new BackupDatabase)->backup();

    Queue::assertPushedOn('emails', SendBackupNotificationJob::class, function (SendBackupNotificationJob $job) {
        return $job->mailable instanceof BackupSuccessMail
            && $job->recipients === ['queued@example.com'];
    });

    Queue::assertPushedOn('emails', SendBackupNotificationJob::class, function (SendBackupNotificationJob $job) {
        return $job->mailable instanceof BackupSummaryMail
            && $job->recipients === ['queued@example.com'];
    });
});
