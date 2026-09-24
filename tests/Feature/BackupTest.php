<?php

use SalvatoreCervone\BackupDatabase\Tests\TestCase;

uses(TestCase::class);

use Illuminate\Support\Facades\Process;
use SalvatoreCervone\BackupDatabase\BackupDatabase;
use SalvatoreCervone\BackupDatabase\DriverManager;
use SalvatoreCervone\BackupDatabase\Drivers\SqlSrvDriver;
use SalvatoreCervone\BackupDatabase\Drivers\MySqlDriver;

// =============================================================================
// SERVICE INSTANTIATION
// =============================================================================

it('can instantiate the service', function () {
    $service = new BackupDatabase();
    expect($service)->toBeInstanceOf(BackupDatabase::class);
});

// =============================================================================
// DRIVER RESOLUTION
// =============================================================================

it('resolves the correct MSSQL driver', function () {
    $driver = DriverManager::make('sqlsrv');
    expect($driver)->toBeInstanceOf(SqlSrvDriver::class);
});

it('resolves the correct MySQL driver', function () {
    $driver = DriverManager::make('mysql');
    expect($driver)->toBeInstanceOf(MySqlDriver::class);
});

it('throws exception for unsupported driver', function () {
    DriverManager::make('unsupported_db');
})->throws(\Exception::class, 'Unsupported database driver: unsupported_db');

// =============================================================================
// BACKUP PROCESS
// =============================================================================

it('executes the MySQL backup process', function () {
    Process::fake();
    
    config(['backup-database.listconnections' => [
        [
            'connection' => 'mysql',
            'destinationpath' => '/tmp/',
            'db_name' => 'test_db',
            'daily' => false,
        ]
    ]]);
    
    config(['database.connections.mysql' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'database' => 'test_db',
        'username' => 'root',
        'password' => 'password',
    ]]);

    $service = new BackupDatabase();
    $results = $service->backup();

    expect($results)->toBeArray();
    Process::assertRan(function ($process) {
        $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;
        return str_contains($command, 'mysqldump');
    });
});

it('executes the MSSQL backup process', function () {
    Process::fake();
    
    config(['backup-database.listconnections' => [
        [
            'connection' => 'sqlsrv_test',
            'destinationpath' => '\\\\server\\backups\\',
            'db_name' => 'TestDB',
            'daily' => true,
            'datetimeFormat' => 'Y-m-d',
        ]
    ]]);
    
    config(['database.connections.sqlsrv_test' => [
        'driver' => 'sqlsrv',
        'host' => 'localhost',
        'database' => 'TestDB',
        'username' => 'sa',
        'password' => 'password123',
    ]]);

    $service = new BackupDatabase();
    $results = $service->backup();

    expect($results)->toBeArray();
    Process::assertRan(function ($process) {
        $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;
        return str_contains($command, 'sqlcmd') && str_contains($command, 'BACKUP DATABASE');
    });
});

it('handles backup failure gracefully', function () {
    Process::fake([
        '*' => Process::result(
            output: '',
            errorOutput: 'Connection refused',
            exitCode: 1,
        ),
    ]);
    
    config(['backup-database.listconnections' => [
        [
            'connection' => 'mysql',
            'destinationpath' => '/tmp/',
            'db_name' => 'test_db',
            'daily' => false,
        ]
    ]]);
    
    config(['database.connections.mysql' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'database' => 'test_db',
        'username' => 'root',
        'password' => 'password',
    ]]);

    $service = new BackupDatabase();
    $results = $service->backup();

    expect($results)->toBeArray()
        ->and($results[0]['status'])->toBeFalse();
});
