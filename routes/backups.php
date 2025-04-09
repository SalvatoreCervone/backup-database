<?php

use Illuminate\Support\Facades\Route;
use Salvatore\BackupDatabase\Facades\BackupDatabase;
use Salvatore\BackupDatabase\BackupDatabase as BackupDatabaseClass;

Route::get('/backups', function () {
    return view('backup-database::backups', [
        'listBackups' => BackupDatabase::getStatus(),
    ]);
})->name('backups.index');



Route::post('/backup/delete', [BackupDatabaseClass::class, 'delete'])->name('backup.delete');

