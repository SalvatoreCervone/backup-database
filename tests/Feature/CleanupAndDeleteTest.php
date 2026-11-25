<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use SalvatoreCervone\BackupDatabase\BackupDatabase;
use SalvatoreCervone\BackupDatabase\Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/backup-db-cleanup-'.uniqid();
    File::ensureDirectoryExists($this->tempDir);
});

afterEach(function () {
    if (isset($this->tempDir) && File::isDirectory($this->tempDir)) {
        File::deleteDirectory($this->tempDir);
    }
});

it('moves file to trash on manual delete when soft_delete is enabled', function () {
    $fileName = 'test_db_2026-10-01_04-00.sql';
    File::put($this->tempDir.'/'.$fileName, 'SQL DUMP');

    config(['backup-database.listconnections' => [
        [
            'connection' => 'mysql',
            'destinationpath' => $this->tempDir.'/',
            'db_name' => 'test_db',
            'soft_delete' => true,
        ],
    ]]);

    $service = new BackupDatabase;
    $result = $service->delete('mysql', $fileName);

    expect($result['status'])->toBeTrue()
        ->and($result['message'])->toContain('trash')
        ->and(File::exists($this->tempDir.'/'.$fileName))->toBeFalse();

    $trashFiles = File::files($this->tempDir.'/trash');
    expect($trashFiles)->toHaveCount(1)
        ->and($trashFiles[0]->getFilename())->toContain($fileName);
});

it('permanently deletes file on manual delete when soft_delete is disabled', function () {
    $fileName = 'test_db_2026-10-01_04-00.sql';
    File::put($this->tempDir.'/'.$fileName, 'SQL DUMP');

    config(['backup-database.listconnections' => [
        [
            'connection' => 'mysql',
            'destinationpath' => $this->tempDir.'/',
            'db_name' => 'test_db',
            'soft_delete' => false,
        ],
    ]]);

    $service = new BackupDatabase;
    $result = $service->delete('mysql', $fileName);

    expect($result['status'])->toBeTrue()
        ->and(File::exists($this->tempDir.'/'.$fileName))->toBeFalse()
        ->and(File::isDirectory($this->tempDir.'/trash'))->toBeFalse();
});

it('runs trash cleanup via artisan backup command and skips newly created backup even with days_for_delete 0', function () {
    Process::fake();

    $oldFile = 'test_db_'.now()->subDays(2)->format('Y-m-d').'_04-00.sql';
    $todayFile = 'test_db_'.now()->format('Y-m-d_H-i').'.sql';

    File::put($this->tempDir.'/'.$oldFile, 'OLD DUMP');
    File::put($this->tempDir.'/'.$todayFile, 'NEW DUMP');

    config(['backup-database.listconnections' => [
        [
            'connection' => 'mysql',
            'destinationpath' => $this->tempDir.'/',
            'db_name' => 'test_db',
            'daily' => true,
            'datetimeFormat' => 'Y-m-d_H-i',
            'days_for_delete' => 0,
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

    $this->artisan('backup-database:backup')->assertSuccessful();

    // Old file moved to trash, newly created file preserved
    expect(File::exists($this->tempDir.'/'.$oldFile))->toBeFalse()
        ->and(File::exists($this->tempDir.'/'.$todayFile))->toBeTrue();

    $trashFiles = File::files($this->tempDir.'/trash');
    expect($trashFiles)->toHaveCount(1)
        ->and($trashFiles[0]->getFilename())->toContain($oldFile);
});
