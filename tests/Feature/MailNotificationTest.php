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
use SalvatoreCervone\BackupDatabase\Support\MailHistoryManager;
use SalvatoreCervone\BackupDatabase\Support\MailSettingsManager;
use SalvatoreCervone\BackupDatabase\Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->settingsFile = sys_get_temp_dir().'/backup-db-mail-settings-'.uniqid().'.json';
    $this->historyFile = sys_get_temp_dir().'/backup-db-mail-history-'.uniqid().'.json';
    config(['backup-database.notifications.settings_path' => $this->settingsFile]);
    config(['backup-database.notifications.history_path' => $this->historyFile]);
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
    if (isset($this->historyFile) && File::exists($this->historyFile)) {
        File::delete($this->historyFile);
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
// SMTP MODE (.ENV VS MANUAL FOR ALL PARAMETERS)
// =============================================================================

it('uses .env mail configuration for all parameters when smtp_mode is env', function () {
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

it('reports unconfigured when smtp_mode is env and .env SMTP host is empty', function () {
    config(['mail.default' => 'smtp']);
    config(['mail.mailers.smtp.host' => null]);

    $manager = new MailSettingsManager;
    $effective = $manager->getEffectiveSmtpConfig();

    expect($effective['source'])->toBe('unconfigured')
        ->and($effective['is_configured'])->toBeFalse();
});

it('uses only manual parameters when smtp_mode is manual, encrypts password, and does not mix with .env', function () {
    $manager = new MailSettingsManager;

    $ui = $manager->save([
        'notification_type' => 'summary',
        'recipients' => ['team@example.com', 'ops@example.com'],
        'smtp_mode' => 'manual',
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
        ->and($ui['smtp_mode'])->toBe('manual')
        ->and($ui['smtp_source'])->toBe('panel')
        ->and($ui['recipients'])->toBe(['team@example.com', 'ops@example.com']);

    // Verify effective config decrypts and uses panel values
    $effective = $manager->getEffectiveSmtpConfig();
    expect($effective['source'])->toBe('panel')
        ->and($effective['host'])->toBe('smtp.custom.test')
        ->and($effective['port'])->toBe(465)
        ->and($effective['username'])->toBe('custom-user')
        ->and($effective['password'])->toBe('super-secret-pass')
        ->and($effective['encryption'])->toBe('ssl')
        ->and($effective['from_address'])->toBe('backup@custom.test')
        ->and($effective['from_name'])->toBe('Custom Backup');

    // Saving again without smtp_password preserves existing password
    $manager->save([
        'notification_type' => 'success',
        'recipients' => ['team@example.com'],
        'smtp_mode' => 'manual',
        'smtp_host' => 'smtp.custom.test',
        'smtp_encryption' => 'tls',
        'smtp_password' => '',
    ]);
    expect($manager->get()['smtp_password'])->toBe('super-secret-pass');

    // Clearing password in manual mode sets password to null (does NOT fall back to .env password!)
    $manager->save([
        'notification_type' => 'success',
        'recipients' => ['team@example.com'],
        'smtp_mode' => 'manual',
        'smtp_host' => 'smtp.custom.test',
        'smtp_encryption' => 'none',
        'clear_smtp_password' => true,
    ]);
    expect($manager->get()['smtp_password'])->toBeNull()
        ->and($manager->getEffectiveSmtpConfig()['password'])->toBeNull()
        ->and($manager->getEffectiveSmtpConfig()['encryption'])->toBeNull();

    // Switching smtp_mode back to env uses ALL .env parameters
    $manager->save([
        'notification_type' => 'failure',
        'recipients' => ['team@example.com'],
        'smtp_mode' => 'env',
    ]);
    $envEffective = $manager->getEffectiveSmtpConfig();
    expect($envEffective['source'])->toBe('env')
        ->and($envEffective['host'])->toBe('smtp.env.test')
        ->and($envEffective['password'])->toBe('env-secret')
        ->and($envEffective['from_address'])->toBe('noreply@env.test');
});

// =============================================================================
// SINGLE NOTIFICATION TYPE (1 OF 3) DURING BACKUP EXECUTION
// =============================================================================

it('sends only BackupSuccessMail when notification_type is success and backup succeeds', function () {
    Mail::fake();
    Process::fake();

    $manager = new MailSettingsManager;
    $manager->save([
        'notification_type' => 'success',
        'recipients' => ['ok@example.com', 'ops@example.com'],
        'smtp_mode' => 'env',
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

    Mail::assertSent(BackupSuccessMail::class, function (BackupSuccessMail $mail) {
        return $mail->hasTo('ok@example.com')
            && $mail->hasTo('ops@example.com')
            && count($mail->backups) === 1;
    });
    Mail::assertNotSent(BackupFailedMail::class);
    Mail::assertNotSent(BackupSummaryMail::class);

    // Verify sent email is recorded in history
    $history = (new MailHistoryManager)->all();
    expect($history)->toHaveCount(1)
        ->and($history[0]['type'])->toBe('success')
        ->and($history[0]['status'])->toBe('sent')
        ->and($history[0]['recipients'])->toBe(['ok@example.com', 'ops@example.com']);
});

it('sends only BackupFailedMail when notification_type is failure and backup fails', function () {
    Mail::fake();
    Process::fake([
        '*' => Process::result(output: '', errorOutput: 'Access denied', exitCode: 1),
    ]);

    $manager = new MailSettingsManager;
    $manager->save([
        'notification_type' => 'failure',
        'recipients' => ['alerts@example.com'],
        'smtp_mode' => 'env',
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

    Mail::assertSent(BackupFailedMail::class, function (BackupFailedMail $mail) {
        return $mail->hasTo('alerts@example.com')
            && $mail->connectionName === 'mysql';
    });
    Mail::assertNotSent(BackupSuccessMail::class);
    Mail::assertNotSent(BackupSummaryMail::class);
});

it('sends ONE single consolidated BackupFailedMail for the entire backup set when multiple connections fail', function () {
    Mail::fake();
    Process::fake([
        '*' => Process::result(output: '', errorOutput: 'Connection refused', exitCode: 1),
    ]);

    $manager = new MailSettingsManager;
    $manager->save([
        'notification_type' => 'failure',
        'recipients' => ['alerts@example.com'],
        'smtp_mode' => 'env',
    ]);

    config(['backup-database.listconnections' => [
        [
            'connection' => 'mysql_a',
            'destinationpath' => '/tmp/',
            'db_name' => 'db_a',
            'daily' => false,
        ],
        [
            'connection' => 'mysql_b',
            'destinationpath' => '/tmp/',
            'db_name' => 'db_b',
            'daily' => false,
        ],
        [
            'connection' => 'mysql_c',
            'destinationpath' => '/tmp/',
            'db_name' => 'db_c',
            'daily' => false,
        ],
    ]]);
    foreach (['mysql_a', 'mysql_b', 'mysql_c'] as $conn) {
        config(["database.connections.{$conn}" => [
            'driver' => 'mysql',
            'host' => 'localhost',
            'database' => $conn,
            'username' => 'root',
            'password' => 'password',
        ]]);
    }

    (new BackupDatabase)->backup();

    Mail::assertSent(BackupFailedMail::class, 1);
    Mail::assertSent(BackupFailedMail::class, function (BackupFailedMail $mail) {
        return $mail->hasTo('alerts@example.com')
            && count($mail->connectionResults) === 3;
    });
});

it('sends only BackupSummaryMail when notification_type is summary', function () {
    Mail::fake();
    Process::fake();

    $manager = new MailSettingsManager;
    $manager->save([
        'notification_type' => 'summary',
        'recipients' => ['summary@example.com'],
        'smtp_mode' => 'env',
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

    Mail::assertSent(BackupSummaryMail::class, function (BackupSummaryMail $mail) {
        return $mail->hasTo('summary@example.com')
            && count($mail->connectionResults) === 1
            && $mail->connectionResults[0]['status'] === true;
    });
    Mail::assertNotSent(BackupSuccessMail::class);
    Mail::assertNotSent(BackupFailedMail::class);
});

it('sends no emails when notification_type is none', function () {
    Mail::fake();
    Process::fake();

    $manager = new MailSettingsManager;
    $manager->save([
        'notification_type' => 'none',
        'recipients' => ['nobody@example.com'],
        'smtp_mode' => 'env',
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

// =============================================================================
// TEST EMAIL (SINGLE RECIPIENT ONLY, NEVER ALL RECIPIENTS)
// =============================================================================

it('sends test email ONLY to the explicitly provided email and never to all recipients', function () {
    Mail::fake();

    $manager = new MailSettingsManager;
    $manager->save([
        'notification_type' => 'success',
        'recipients' => ['all1@example.com', 'all2@example.com'],
        'smtp_mode' => 'manual',
        'smtp_host' => 'smtp.override.test',
        'smtp_port' => 2525,
        'smtp_username' => 'override-user',
        'smtp_password' => 'override-pass',
        'smtp_encryption' => 'tls',
    ]);

    $notifier = new BackupNotifier($manager);

    // Calling sendTest without an email must fail and not send anything
    $missingResult = $notifier->sendTest();
    expect($missingResult['status'])->toBeFalse();
    Mail::assertNothingSent();

    // Calling sendTest with a specific email sends ONLY to that email
    $result = $notifier->sendTest('single-test@example.com');

    expect($result['status'])->toBeTrue()
        ->and($result['smtp_source'])->toBe('panel')
        ->and(config('mail.mailers.backup_database_smtp.host'))->toBe('smtp.override.test')
        ->and(config('mail.mailers.backup_database_smtp.port'))->toBe(2525)
        ->and(config('mail.mailers.backup_database_smtp.username'))->toBe('override-user')
        ->and(config('mail.mailers.backup_database_smtp.password'))->toBe('override-pass');

    Mail::assertSent(TestNotificationMail::class, function (TestNotificationMail $mail) {
        return $mail->hasTo('single-test@example.com')
            && ! $mail->hasTo('all1@example.com')
            && ! $mail->hasTo('all2@example.com');
    });

    // Verify test email recorded in history
    $history = (new MailHistoryManager)->all();
    expect($history)->toHaveCount(1)
        ->and($history[0]['type'])->toBe('test')
        ->and($history[0]['recipients'])->toBe(['single-test@example.com']);
});

// =============================================================================
// HTTP ENDPOINTS & SECURITY
// =============================================================================

it('protects mail settings and history routes for unauthenticated users', function () {
    expect($this->getJson(route('backup.settings.mail.get'))->status())->toBeIn([401, 403]);
    expect($this->postJson(route('backup.settings.mail.save'), [])->status())->toBeIn([401, 403]);
    expect($this->postJson(route('backup.settings.mail.test'), [])->status())->toBeIn([401, 403]);
    expect($this->getJson(route('backup.settings.mail.history'))->status())->toBeIn([401, 403]);
});

it('validates settings, requires single recipient for test mail, and returns sent mail history', function () {
    Mail::fake();

    $user = new class extends User
    {
        protected $guarded = [];
    };
    $user->id = 1;

    // Invalid recipient email should fail validation (422)
    $this->actingAs($user)
        ->postJson(route('backup.settings.mail.save'), [
            'notification_type' => 'success',
            'recipients' => ['valid@example.com', 'not-an-email'],
            'smtp_mode' => 'env',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipients']);

    // Manual mode without smtp_host or invalid encryption should fail validation (422)
    $this->actingAs($user)
        ->postJson(route('backup.settings.mail.save'), [
            'notification_type' => 'success',
            'recipients' => ['admin@example.com'],
            'smtp_mode' => 'manual',
            'smtp_host' => '',
            'smtp_encryption' => 'env',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['smtp_host', 'smtp_encryption']);

    // Valid save should succeed
    $this->actingAs($user)
        ->postJson(route('backup.settings.mail.save'), [
            'notification_type' => 'summary',
            'recipients' => ['admin@example.com', 'ops@example.com'],
            'smtp_mode' => 'env',
        ])
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('settings.notification_type', 'summary')
        ->assertJsonPath('settings.smtp_mode', 'env')
        ->assertJsonPath('settings.smtp_source', 'env');

    // GET settings returns persisted values
    $this->actingAs($user)
        ->getJson(route('backup.settings.mail.get'))
        ->assertOk()
        ->assertJsonPath('notification_type', 'summary')
        ->assertJsonPath('recipients', ['admin@example.com', 'ops@example.com'])
        ->assertJsonPath('smtp_mode', 'env');

    // Test email without recipient fails validation (422)
    $this->actingAs($user)
        ->postJson(route('backup.settings.mail.test'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recipient']);

    // Test email with single recipient succeeds and sends ONLY to that address
    $this->actingAs($user)
        ->postJson(route('backup.settings.mail.test'), ['recipient' => 'qa@example.com'])
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('smtp_source', 'env');

    Mail::assertSent(TestNotificationMail::class, function (TestNotificationMail $mail) {
        return $mail->hasTo('qa@example.com')
            && ! $mail->hasTo('admin@example.com')
            && ! $mail->hasTo('ops@example.com');
    });

    // History endpoint returns the sent test email
    $this->actingAs($user)
        ->getJson(route('backup.settings.mail.history'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'test')
        ->assertJsonPath('data.0.recipients', ['qa@example.com'])
        ->assertJsonPath('data.0.status', 'sent');
});

it('renders the dashboard view with mini menu, 1-of-3 radios, recipients datatable, smtp mode, and test mail input', function () {
    $user = new class extends User
    {
        protected $guarded = [];
    };
    $user->id = 1;

    config(['backup-database.listconnections' => []]);

    $this->actingAs($user)
        ->get(route('backups.index'))
        ->assertOk()
        ->assertSee('Impostazioni')
        ->assertSee('Elenco Email Inviate')
        ->assertSee('Tipologia di Email (seleziona 1 delle 3)')
        ->assertSee('+ Aggiungi')
        ->assertSee('Usa parametri da')
        ->assertSee('Immissione manuale')
        ->assertSee('Test Invio Email')
        ->assertSee('SMTP: .env');
});

it('dispatches backup notification emails to the Laravel queue via SendBackupNotificationJob', function () {
    Queue::fake();
    Process::fake();

    config(['backup-database.notifications.use_queue' => true]);
    config(['backup-database.notifications.queue_name' => 'emails']);

    $manager = new MailSettingsManager;
    $manager->save([
        'notification_type' => 'summary',
        'recipients' => ['queued@example.com'],
        'smtp_mode' => 'env',
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
        return $job->mailable instanceof BackupSummaryMail
            && $job->recipients === ['queued@example.com'];
    });
    Queue::assertPushed(SendBackupNotificationJob::class, 1);
});

it('dispatches test email through SendBackupNotificationJob when use_queue is enabled', function () {
    Queue::fake();

    config(['backup-database.notifications.use_queue' => true]);
    config(['backup-database.notifications.queue_name' => 'emails']);

    $notifier = new BackupNotifier;
    $result = $notifier->sendTest('queued-test@example.com');

    expect($result['status'])->toBeTrue()
        ->and($result['queued'])->toBeTrue();

    Queue::assertPushedOn('emails', SendBackupNotificationJob::class, function (SendBackupNotificationJob $job) {
        return $job->mailable instanceof TestNotificationMail
            && $job->recipients === ['queued-test@example.com'];
    });
});
