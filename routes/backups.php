<?php

use Illuminate\Support\Facades\Route;
use SalvatoreCervone\BackupDatabase\Http\Controllers\BackupController;
use SalvatoreCervone\BackupDatabase\Http\Middleware\AuthorizeBackupAccess;

$middleware = array_merge(
    config('backup-database.middleware', ['web']),
    [AuthorizeBackupAccess::class]
);

$prefix = config('backup-database.route_prefix', 'backups');

Route::middleware($middleware)->prefix($prefix)->group(function () {
    Route::get('/', [BackupController::class, 'index'])->name('backups.index');
    Route::post('/create', [BackupController::class, 'create'])->name('backup.create');
    Route::post('/delete', [BackupController::class, 'delete'])->name('backup.delete');
    Route::post('/restore', [BackupController::class, 'restore'])->name('backup.restore');
    Route::get('/logs', [BackupController::class, 'logs'])->name('backup.logs');
    Route::get('/settings/mail', [BackupController::class, 'getMailSettings'])->name('backup.settings.mail.get');
    Route::post('/settings/mail', [BackupController::class, 'saveMailSettings'])->name('backup.settings.mail.save');
    Route::post('/settings/mail/test', [BackupController::class, 'testMailSettings'])->name('backup.settings.mail.test');
    Route::get('/settings/mail/history', [BackupController::class, 'getMailHistory'])->name('backup.settings.mail.history');
});
