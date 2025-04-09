<?php

use Illuminate\Support\Facades\Route;
use Salvatore\BackupDatabase\Facades\BackupDatabase;
use Salvatore\BackupDatabase\BackupDatabase as BackupDatabaseClass;

Route::get('/backups', function () {
    return view('backup-database::backups', [
        'listBackups' => BackupDatabase::getStatus(),
    ]);
})->name('backups.index');

Route::get(
    '/backup/delete',
    function () {
        return view('backup-database::backup-delete', [
            'file' => request('file'),
        ]);
    }
)->name('backup.delete.get');

Route::post('/backup/delete', [BackupDatabaseClass::class, 'delete'])->name('backup.delete');
Route::post('/backup/download', [BackupDatabaseClass::class, 'download'])->name('backup.download');
