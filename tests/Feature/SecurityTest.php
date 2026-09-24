<?php

use SalvatoreCervone\BackupDatabase\Tests\TestCase;

uses(TestCase::class);

use SalvatoreCervone\BackupDatabase\Security\PathValidator;
use SalvatoreCervone\BackupDatabase\Exceptions\BackupException;

// =============================================================================
// PATH TRAVERSAL PROTECTION
// =============================================================================

it('rejects filenames with directory separators', function () {
    config(['backup-database.listconnections' => [
        ['connection' => 'mysql', 'destinationpath' => '/var/backups/']
    ]]);

    PathValidator::validateFileName('../../etc/passwd', 'mysql');
})->throws(BackupException::class, 'caratteri non validi');

it('rejects filenames with backslash directory separators', function () {
    config(['backup-database.listconnections' => [
        ['connection' => 'mysql', 'destinationpath' => '/var/backups/']
    ]]);

    PathValidator::validateFileName('..\\..\\windows\\system32\\config', 'mysql');
})->throws(BackupException::class, 'caratteri non validi');

it('rejects filenames containing double dots', function () {
    config(['backup-database.listconnections' => [
        ['connection' => 'mysql', 'destinationpath' => '/var/backups/']
    ]]);

    PathValidator::validateFileName('..myfile.sql', 'mysql');
})->throws(BackupException::class, 'caratteri non validi');

it('rejects empty filenames', function () {
    config(['backup-database.listconnections' => [
        ['connection' => 'mysql', 'destinationpath' => '/var/backups/']
    ]]);

    PathValidator::validateFileName('', 'mysql');
})->throws(BackupException::class, 'vuoto');

it('rejects unknown connection names', function () {
    config(['backup-database.listconnections' => [
        ['connection' => 'mysql', 'destinationpath' => '/var/backups/']
    ]]);

    PathValidator::validateFileName('backup.sql', 'nonexistent_connection');
})->throws(BackupException::class, 'non trovata');

it('accepts valid basename filenames', function () {
    config(['backup-database.listconnections' => [
        ['connection' => 'mysql', 'destinationpath' => '/var/backups/']
    ]]);

    $result = PathValidator::validateFileName('mydb_2026-09-24.sql', 'mysql');

    expect($result)->toContain('mydb_2026-09-24.sql')
        ->and($result)->toContain('backups');
});

// =============================================================================
// PATH VALIDATION (full path)
// =============================================================================

it('rejects paths outside allowed directories', function () {
    $allowedPaths = ['/var/backups/'];

    PathValidator::validate('/etc/passwd', $allowedPaths);
})->throws(BackupException::class, 'Accesso negato');

it('rejects SMB paths with traversal patterns', function () {
    $allowedPaths = ['//server/share/backups/'];

    PathValidator::validate('//server/share/backups/../../secret', $allowedPaths);
})->throws(BackupException::class, 'attraversamento');

it('rejects empty paths', function () {
    PathValidator::validate('', ['/var/backups/']);
})->throws(BackupException::class, 'vuoto');

// =============================================================================
// ROUTE PROTECTION (requires authentication middleware)
// =============================================================================

it('returns 401 for unauthenticated users on dashboard', function () {
    $response = $this->getJson(route('backups.index'));

    // JSON request returns 401 when auth middleware rejects it
    expect($response->status())->toBeIn([401, 403]);
});

it('returns 401 for unauthenticated users on create backup', function () {
    $response = $this->postJson(route('backup.create'));

    expect($response->status())->toBeIn([401, 403]);
});

it('returns 401 for unauthenticated users on delete backup', function () {
    $response = $this->postJson(route('backup.delete'), [
        'file' => 'test.sql',
        'connection' => 'mysql',
    ]);

    expect($response->status())->toBeIn([401, 403]);
});

it('returns 401 for unauthenticated users on restore backup', function () {
    $response = $this->postJson(route('backup.restore'), [
        'file' => 'test.sql',
        'connection' => 'mysql',
    ]);

    expect($response->status())->toBeIn([401, 403]);
});
