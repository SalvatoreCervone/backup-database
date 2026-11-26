<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use SalvatoreCervone\BackupDatabase\BackupDatabase;
use SalvatoreCervone\BackupDatabase\Exceptions\BackupException;
use SalvatoreCervone\BackupDatabase\Security\PathValidator;
use SalvatoreCervone\BackupDatabase\Support\TrashSettingsManager;
use SalvatoreCervone\BackupDatabase\Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/backup-db-trash-'.uniqid();
    $this->trashSettingsFile = sys_get_temp_dir().'/backup-db-trash-settings-'.uniqid().'.json';

    File::ensureDirectoryExists($this->tempDir);

    config(['backup-database.trash.settings_path' => $this->trashSettingsFile]);
    config(['backup-database.trash.auto_delete' => false]);
    config(['backup-database.trash.keep_copies' => 2]);
    config(['backup-database.trash.days_for_delete' => null]);
    config(['backup-database.enable_logging' => false]);
    config(['backup-database.notification_email' => null]);
});

afterEach(function () {
    if (isset($this->tempDir) && File::isDirectory($this->tempDir)) {
        File::deleteDirectory($this->tempDir);
    }

    if (isset($this->trashSettingsFile) && File::exists($this->trashSettingsFile)) {
        File::delete($this->trashSettingsFile);
    }
});

// =============================================================================
// TRASH SETTINGS MANAGER
// =============================================================================

it('returns default trash settings from config when no JSON file exists', function () {
    $manager = new TrashSettingsManager;

    expect($manager->get())->toBe([
        'auto_delete' => false,
        'keep_copies' => 2,
        'days_for_delete' => null,
    ]);

    config([
        'backup-database.trash.auto_delete' => true,
        'backup-database.trash.keep_copies' => 5,
        'backup-database.trash.days_for_delete' => 14,
    ]);

    expect($manager->get())->toBe([
        'auto_delete' => true,
        'keep_copies' => 5,
        'days_for_delete' => 14,
    ]);
});

it('saves and reloads trash settings from JSON file', function () {
    $manager = new TrashSettingsManager;

    $saved = $manager->save([
        'auto_delete' => true,
        'keep_copies' => 4,
        'days_for_delete' => 30,
    ]);

    expect($saved)->toBe([
        'auto_delete' => true,
        'keep_copies' => 4,
        'days_for_delete' => 30,
    ])->and(File::exists($this->trashSettingsFile))->toBeTrue();

    // Fresh instance reads back persisted JSON values
    $reloaded = (new TrashSettingsManager)->get();

    expect($reloaded)->toBe([
        'auto_delete' => true,
        'keep_copies' => 4,
        'days_for_delete' => 30,
    ]);

    // Saving empty string for days_for_delete resets it to null and clamps negatives to 0
    $updated = $manager->save([
        'auto_delete' => false,
        'keep_copies' => -3,
        'days_for_delete' => '',
    ]);

    expect($updated)->toBe([
        'auto_delete' => false,
        'keep_copies' => 0,
        'days_for_delete' => null,
    ]);
});

it('resolves per-connection overrides with fallback to global trash settings', function () {
    $manager = new TrashSettingsManager;
    $manager->save([
        'auto_delete' => true,
        'keep_copies' => 3,
        'days_for_delete' => 10,
    ]);

    // Without overrides -> inherits global settings
    expect($manager->resolveForConnection(['connection' => 'mysql']))->toBe([
        'auto_delete' => true,
        'keep_copies' => 3,
        'days_for_delete' => 10,
    ]);

    // With full per-connection overrides
    expect($manager->resolveForConnection([
        'connection' => 'mysql',
        'trash_auto_delete' => false,
        'trash_keep_copies' => 5,
        'trash_days_for_delete' => 21,
    ]))->toBe([
        'auto_delete' => false,
        'keep_copies' => 5,
        'days_for_delete' => 21,
    ]);

    // Explicit null on trash_days_for_delete disables age filter for this connection
    expect($manager->resolveForConnection([
        'connection' => 'mysql',
        'trash_days_for_delete' => null,
    ]))->toBe([
        'auto_delete' => true,
        'keep_copies' => 3,
        'days_for_delete' => null,
    ]);
});

// =============================================================================
// AUTOMATIC TRASH CLEANUP DURING BACKUP
// =============================================================================

it('does not delete any trashed files during backup when auto_delete is false', function () {
    Process::fake();

    $trashDir = $this->tempDir.'/trash';
    File::ensureDirectoryExists($trashDir);

    $files = [
        '20260101_010000_test_db_2026-01-01.sql',
        '20260102_010000_test_db_2026-01-02.sql',
        '20260103_010000_test_db_2026-01-03.sql',
        '20260104_010000_test_db_2026-01-04.sql',
        '20260105_010000_test_db_2026-01-05.sql',
    ];

    foreach ($files as $file) {
        File::put($trashDir.'/'.$file, 'TRASHED SQL');
    }

    (new TrashSettingsManager)->save([
        'auto_delete' => false,
        'keep_copies' => 2,
        'days_for_delete' => null,
    ]);

    config(['backup-database.listconnections' => [
        [
            'connection' => 'mysql',
            'destinationpath' => $this->tempDir.'/',
            'db_name' => 'test_db',
            'days_for_delete' => null,
            'soft_delete' => true,
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

    expect(File::files($trashDir))->toHaveCount(5);
    foreach ($files as $file) {
        expect(File::exists($trashDir.'/'.$file))->toBeTrue();
    }
});

it('does not delete any trashed files during backup when count is less than or equal to keep_copies', function () {
    Process::fake();

    $trashDir = $this->tempDir.'/trash';
    File::ensureDirectoryExists($trashDir);

    $files = [
        '20260101_010000_test_db_2026-01-01.sql',
        '20260102_010000_test_db_2026-01-02.sql',
    ];

    foreach ($files as $file) {
        File::put($trashDir.'/'.$file, 'TRASHED SQL');
    }

    (new TrashSettingsManager)->save([
        'auto_delete' => true,
        'keep_copies' => 2,
        'days_for_delete' => null,
    ]);

    config(['backup-database.listconnections' => [
        [
            'connection' => 'mysql',
            'destinationpath' => $this->tempDir.'/',
            'db_name' => 'test_db',
            'days_for_delete' => null,
            'soft_delete' => true,
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

    expect(File::files($trashDir))->toHaveCount(2);
    foreach ($files as $file) {
        expect(File::exists($trashDir.'/'.$file))->toBeTrue();
    }
});

it('permanently deletes only the 3 oldest trashed files and preserves the 2 newest plus other database files', function () {
    Process::fake();

    $trashDir = $this->tempDir.'/trash';
    File::ensureDirectoryExists($trashDir);

    $oldest1 = '20260101_080000_test_db_2026-01-01.sql';
    $oldest2 = '20260102_090000_test_db_2026-01-02.sql';
    $oldest3 = '20260103_100000_test_db_2026-01-03.sql';
    $newest2 = '20260104_110000_test_db_2026-01-04.sql';
    $newest1 = '20260105_120000_test_db_2026-01-05.sql';

    $otherDb1 = '20250101_080000_other_db_2025-01-01.sql';
    $otherDb2 = '20250102_080000_other_db_2025-01-02.sql';
    $otherDb3 = '20250103_080000_other_db_2025-01-03.sql';

    foreach ([$oldest1, $oldest2, $oldest3, $newest2, $newest1, $otherDb1, $otherDb2, $otherDb3] as $file) {
        File::put($trashDir.'/'.$file, 'TRASHED SQL');
    }

    (new TrashSettingsManager)->save([
        'auto_delete' => true,
        'keep_copies' => 2,
        'days_for_delete' => null,
    ]);

    config(['backup-database.listconnections' => [
        [
            'connection' => 'mysql',
            'destinationpath' => $this->tempDir.'/',
            'db_name' => 'test_db',
            'days_for_delete' => null,
            'soft_delete' => true,
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

    // 3 oldest for test_db deleted
    expect(File::exists($trashDir.'/'.$oldest1))->toBeFalse()
        ->and(File::exists($trashDir.'/'.$oldest2))->toBeFalse()
        ->and(File::exists($trashDir.'/'.$oldest3))->toBeFalse();

    // 2 newest for test_db preserved
    expect(File::exists($trashDir.'/'.$newest2))->toBeTrue()
        ->and(File::exists($trashDir.'/'.$newest1))->toBeTrue();

    // All files for other_db untouched
    expect(File::exists($trashDir.'/'.$otherDb1))->toBeTrue()
        ->and(File::exists($trashDir.'/'.$otherDb2))->toBeTrue()
        ->and(File::exists($trashDir.'/'.$otherDb3))->toBeTrue();
});

it('respects days_for_delete by preserving recent excess copies and always keeping keep_copies newest even if older', function () {
    Process::fake();

    $trashDir = $this->tempDir.'/trash';
    File::ensureDirectoryExists($trashDir);

    // Scenario 1: 2 newest (1d, 2d), 1 excess newer than 7d (4d), 2 excess older than 7d (10d, 15d)
    $newest1 = now()->subDays(1)->format('Ymd_His').'_test_db_1.sql';
    $newest2 = now()->subDays(2)->format('Ymd_His').'_test_db_2.sql';
    $excessRecent = now()->subDays(4)->format('Ymd_His').'_test_db_3.sql';
    $excessOld1 = now()->subDays(10)->format('Ymd_His').'_test_db_4.sql';
    $excessOld2 = now()->subDays(15)->format('Ymd_His').'_test_db_5.sql';

    foreach ([$newest1, $newest2, $excessRecent, $excessOld1, $excessOld2] as $file) {
        File::put($trashDir.'/'.$file, 'TRASHED SQL');
    }

    (new TrashSettingsManager)->save([
        'auto_delete' => true,
        'keep_copies' => 2,
        'days_for_delete' => 7,
    ]);

    config(['backup-database.listconnections' => [
        [
            'connection' => 'mysql',
            'destinationpath' => $this->tempDir.'/',
            'db_name' => 'test_db',
            'days_for_delete' => null,
            'soft_delete' => true,
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

    expect(File::exists($trashDir.'/'.$newest1))->toBeTrue()
        ->and(File::exists($trashDir.'/'.$newest2))->toBeTrue()
        ->and(File::exists($trashDir.'/'.$excessRecent))->toBeTrue()
        ->and(File::exists($trashDir.'/'.$excessOld1))->toBeFalse()
        ->and(File::exists($trashDir.'/'.$excessOld2))->toBeFalse();

    // Scenario 2: ALL copies are older than 7 days -> 2 newest are STILL preserved
    File::cleanDirectory($trashDir);

    $oldNewest1 = now()->subDays(10)->format('Ymd_His').'_test_db_'.now()->subDays(10)->format('Y-m-d').'.sql';
    $oldNewest2 = now()->subDays(12)->format('Ymd_His').'_test_db_'.now()->subDays(12)->format('Y-m-d').'.sql';
    $oldExcess1 = now()->subDays(20)->format('Ymd_His').'_test_db_'.now()->subDays(20)->format('Y-m-d').'.sql';
    $oldExcess2 = now()->subDays(25)->format('Ymd_His').'_test_db_'.now()->subDays(25)->format('Y-m-d').'.sql';

    foreach ([$oldNewest1, $oldNewest2, $oldExcess1, $oldExcess2] as $file) {
        File::put($trashDir.'/'.$file, 'TRASHED SQL');
    }

    (new BackupDatabase)->backup();

    expect(File::exists($trashDir.'/'.$oldNewest1))->toBeTrue()
        ->and(File::exists($trashDir.'/'.$oldNewest2))->toBeTrue()
        ->and(File::exists($trashDir.'/'.$oldExcess1))->toBeFalse()
        ->and(File::exists($trashDir.'/'.$oldExcess2))->toBeFalse();
});

// =============================================================================
// TRASH STATUS LISTING
// =============================================================================

it('returns empty array cleanly from getTrashStatus when trash directory does not exist', function () {
    config(['backup-database.listconnections' => [
        [
            'connection' => 'mysql',
            'destinationpath' => $this->tempDir.'/',
            'db_name' => 'test_db',
        ],
    ]]);

    $status = (new BackupDatabase)->getTrashStatus();

    expect($status)->toHaveKey('mysql')
        ->and($status['mysql'])->toBe([]);
});

it('lists .bak and .sql files in trash sorted newest-first', function () {
    $trashDir = $this->tempDir.'/trash';
    File::ensureDirectoryExists($trashDir);

    $fileOld = '20260101_080000_test_db.sql';
    $fileMid = '20260201_080000_test_db.bak';
    $fileNew = '20260301_080000_test_db.sql';
    $ignored = '20260401_080000_notes.txt';

    foreach ([$fileOld, $fileMid, $fileNew, $ignored] as $file) {
        File::put($trashDir.'/'.$file, 'CONTENT');
    }

    config(['backup-database.listconnections' => [
        [
            'connection' => 'mysql',
            'destinationpath' => $this->tempDir.'/',
            'db_name' => 'test_db',
        ],
    ]]);

    $status = (new BackupDatabase)->getTrashStatus();

    expect($status['mysql'])->toHaveCount(3)
        ->and(array_column($status['mysql'], 'name'))->toBe([
            $fileNew,
            $fileMid,
            $fileOld,
        ]);
});

// =============================================================================
// DELETE FROM TRASH & PATH VALIDATOR
// =============================================================================

it('permanently deletes a valid file via deleteFromTrash', function () {
    $trashDir = $this->tempDir.'/trash';
    File::ensureDirectoryExists($trashDir);

    $fileName = '20260301_120000_test_db.sql';
    File::put($trashDir.'/'.$fileName, 'TRASHED SQL');

    config(['backup-database.listconnections' => [
        [
            'connection' => 'mysql',
            'destinationpath' => $this->tempDir.'/',
            'db_name' => 'test_db',
        ],
    ]]);

    $result = (new BackupDatabase)->deleteFromTrash('mysql', $fileName);

    expect($result['status'])->toBeTrue()
        ->and(File::exists($trashDir.'/'.$fileName))->toBeFalse();
});

it('validates trash file names and rejects path traversal, slashes, empty names, and unknown connections', function () {
    config(['backup-database.listconnections' => [
        [
            'connection' => 'mysql',
            'destinationpath' => $this->tempDir.'/',
            'db_name' => 'test_db',
        ],
    ]]);

    $validPath = PathValidator::validateTrashFileName('20260301_120000_test_db.sql', 'mysql');
    expect($validPath)->toBe(rtrim($this->tempDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'trash'.DIRECTORY_SEPARATOR.'20260301_120000_test_db.sql');

    expect(fn () => PathValidator::validateTrashFileName('../secret.sql', 'mysql'))
        ->toThrow(BackupException::class);

    expect(fn () => PathValidator::validateTrashFileName('..', 'mysql'))
        ->toThrow(BackupException::class);

    expect(fn () => PathValidator::validateTrashFileName('subdir/file.sql', 'mysql'))
        ->toThrow(BackupException::class);

    expect(fn () => PathValidator::validateTrashFileName('subdir\\file.sql', 'mysql'))
        ->toThrow(BackupException::class);

    expect(fn () => PathValidator::validateTrashFileName('', 'mysql'))
        ->toThrow(BackupException::class);

    expect(fn () => PathValidator::validateTrashFileName('20260301_120000_test_db.sql', 'unknown_connection'))
        ->toThrow(BackupException::class);

    // deleteFromTrash catches validation errors and returns status false
    $failed = (new BackupDatabase)->deleteFromTrash('mysql', '../escape.sql');
    expect($failed['status'])->toBeFalse();
});

// =============================================================================
// HTTP ROUTES & AUTHORIZATION
// =============================================================================

it('protects trash settings and trash management routes for unauthenticated users', function () {
    expect($this->getJson(route('backup.settings.trash.get'))->status())->toBeIn([401, 403]);
    expect($this->postJson(route('backup.settings.trash.save'), [])->status())->toBeIn([401, 403]);
    expect($this->getJson(route('backup.trash.index'))->status())->toBeIn([401, 403]);
    expect($this->postJson(route('backup.trash.delete'), [])->status())->toBeIn([401, 403]);
    expect($this->postJson(route('backup.trash.cleanup'))->status())->toBeIn([401, 403]);
});

it('allows authenticated users to validate and save trash settings, view trash, delete files, and trigger cleanup', function () {
    $user = new class extends User
    {
        protected $guarded = [];
    };
    $user->id = 1;

    $trashDir = $this->tempDir.'/trash';
    File::ensureDirectoryExists($trashDir);

    $file1 = '20260101_080000_test_db.sql';
    $file2 = '20260102_080000_test_db.sql';
    $file3 = '20260103_080000_test_db.sql';
    $file4 = '20260104_080000_test_db.sql';
    $file5 = '20260105_080000_test_db.sql';

    foreach ([$file1, $file2, $file3, $file4, $file5] as $file) {
        File::put($trashDir.'/'.$file, 'TRASHED SQL');
    }

    config(['backup-database.listconnections' => [
        [
            'connection' => 'mysql',
            'destinationpath' => $this->tempDir.'/',
            'db_name' => 'test_db',
        ],
    ]]);

    // Negative keep_copies fails validation (422)
    $this->actingAs($user)
        ->postJson(route('backup.settings.trash.save'), [
            'auto_delete' => true,
            'keep_copies' => -1,
            'days_for_delete' => null,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['keep_copies']);

    // Save valid trash settings
    $this->actingAs($user)
        ->postJson(route('backup.settings.trash.save'), [
            'auto_delete' => false,
            'keep_copies' => 2,
            'days_for_delete' => null,
        ])
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('settings.auto_delete', false)
        ->assertJsonPath('settings.keep_copies', 2)
        ->assertJsonPath('settings.days_for_delete', null);

    // GET trash settings returns saved values
    $this->actingAs($user)
        ->getJson(route('backup.settings.trash.get'))
        ->assertOk()
        ->assertJson([
            'auto_delete' => false,
            'keep_copies' => 2,
            'days_for_delete' => null,
        ]);

    // GET trash list returns all 5 files sorted newest-first
    $this->actingAs($user)
        ->getJson(route('backup.trash.index'))
        ->assertOk()
        ->assertJsonCount(5, 'data.mysql')
        ->assertJsonPath('data.mysql.0.name', $file5)
        ->assertJsonPath('data.mysql.4.name', $file1);

    // Manually delete a single file from trash
    $this->actingAs($user)
        ->postJson(route('backup.trash.delete'), [
            'connection' => 'mysql',
            'file' => $file5,
        ])
        ->assertOk()
        ->assertJsonPath('status', true);

    expect(File::exists($trashDir.'/'.$file5))->toBeFalse();

    // Path traversal via HTTP endpoint returns 422
    $this->actingAs($user)
        ->postJson(route('backup.trash.delete'), [
            'connection' => 'mysql',
            'file' => '../evil.sql',
        ])
        ->assertStatus(422)
        ->assertJsonPath('status', false);

    // Trigger manual cleanup (POST /backups/trash/cleanup) — runs even with auto_delete=false
    // Remaining files: file4, file3, file2, file1 -> keeps 2 newest (file4, file3), deletes 2 oldest (file2, file1)
    $this->actingAs($user)
        ->postJson(route('backup.trash.cleanup'))
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('deleted', 2)
        ->assertJsonCount(2, 'trash.mysql')
        ->assertJsonPath('trash.mysql.0.name', $file4)
        ->assertJsonPath('trash.mysql.1.name', $file3);

    expect(File::exists($trashDir.'/'.$file4))->toBeTrue()
        ->and(File::exists($trashDir.'/'.$file3))->toBeTrue()
        ->and(File::exists($trashDir.'/'.$file2))->toBeFalse()
        ->and(File::exists($trashDir.'/'.$file1))->toBeFalse();
});

it('rejects SMB wildcards, command injection characters, and non-backup extensions in validateTrashFileName', function () {
    config(['backup-database.listconnections' => [
        [
            'connection' => 'mysql',
            'destinationpath' => $this->tempDir.'/',
            'db_name' => 'test_db',
        ],
    ]]);

    foreach (['*.bak', 'file?.sql', 'x"; !id; #.bak', '.htaccess', 'notes.txt'] as $malicious) {
        expect(fn () => PathValidator::validateTrashFileName($malicious, 'mysql'))
            ->toThrow(BackupException::class);
    }
});

it('does not collide across databases with shared prefixes like app vs app_logs', function () {
    Process::fake();

    $trashDir = $this->tempDir.'/trash';
    File::ensureDirectoryExists($trashDir);

    $app1 = '20260101_020000_app_2026-01-01.sql';
    $app2 = '20260102_020000_app_2026-01-02.sql';
    $app3 = '20260103_020000_app_2026-01-03.sql';
    $appLogs1 = '20260101_020000_app_logs_2026-01-01.sql';
    $appLogs2 = '20260102_020000_app_logs_2026-01-02.sql';

    foreach ([$app1, $app2, $app3, $appLogs1, $appLogs2] as $f) {
        File::put($trashDir.'/'.$f, 'SQL');
    }

    (new TrashSettingsManager)->save([
        'auto_delete' => true,
        'keep_copies' => 2,
        'days_for_delete' => null,
    ]);

    config(['backup-database.listconnections' => [
        [
            'connection' => 'app_conn',
            'destinationpath' => $this->tempDir.'/',
            'db_name' => 'app',
            'daily' => false,
            'days_for_delete' => null,
            'soft_delete' => true,
        ],
    ]]);
    config(['database.connections.app_conn' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'database' => 'app',
        'username' => 'root',
        'password' => 'password',
    ]]);

    (new BackupDatabase)->backup();

    // Only oldest 'app' copy deleted; both 'app_logs' copies remain untouched
    expect(File::exists($trashDir.'/'.$app1))->toBeFalse()
        ->and(File::exists($trashDir.'/'.$app2))->toBeTrue()
        ->and(File::exists($trashDir.'/'.$app3))->toBeTrue()
        ->and(File::exists($trashDir.'/'.$appLogs1))->toBeTrue()
        ->and(File::exists($trashDir.'/'.$appLogs2))->toBeTrue();
});

it('renders the dashboard with the Cestino (Trash) tab and trash retention settings card', function () {
    $user = new class extends User
    {
        protected $guarded = [];
    };
    $user->id = 1;

    config(['backup-database.listconnections' => []]);

    $this->actingAs($user)
        ->get(route('backups.index'))
        ->assertOk()
        ->assertSee('Cestino (Trash)')
        ->assertSee('Impostazioni Cestino (Trash)')
        ->assertSee('Copie minime da mantenere in trash')
        ->assertSee('Pulisci Cestino Ora');
});
