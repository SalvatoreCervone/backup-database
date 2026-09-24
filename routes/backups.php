<?php

use Illuminate\Support\Facades\Route;
use SalvatoreCervone\BackupDatabase\Http\Controllers\BackupController;
use SalvatoreCervone\BackupDatabase\Http\Middleware\AuthorizeBackupAccess;

$middleware = array_merge(
    config('backup-database.middleware', ['web', 'auth']),
    [AuthorizeBackupAccess::class]
);

$prefix = config('backup-database.route_prefix', 'backups');

Route::middleware($middleware)->prefix($prefix)->group(function () {
    Route::get('/', [BackupController::class, 'index'])->name('backups.index');
    Route::post('/create', [BackupController::class, 'create'])->name('backup.create');
    Route::post('/delete', [BackupController::class, 'delete'])->name('backup.delete');
    Route::post('/restore', [BackupController::class, 'restore'])->name('backup.restore');
    Route::get('/logs', [BackupController::class, 'logs'])->name('backup.logs');
});
