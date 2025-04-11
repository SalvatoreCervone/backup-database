<?php

use Illuminate\Support\Facades\Route;
use SalvatoreCervone\BackupDatabase\Facades\BackupDatabase;
use SalvatoreCervone\BackupDatabase\BackupDatabase as BackupDatabaseClass;

Route::get('/backups', function () {
    return view('backup-database::backups', [
        'listBackups' => BackupDatabase::getStatus(),
    ]);
})->name('backups.index');



Route::post('/backup/delete', [BackupDatabaseClass::class, 'delete'])->name('backup.delete');
Route::post('/backup/create', [BackupDatabaseClass::class, 'backup'])->name('backup.create');
