<?php

namespace SalvatoreCervone\BackupDatabase;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use SalvatoreCervone\BackupDatabase\Commands\BackupDatabaseCommand;

class BackupDatabaseServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {

        $package
            ->name('backup-database')
            ->hasConfigFile()
            ->hasViews()
            ->hasRoute('backups')
            // ->hasMigration('create_backup_database_table')
            ->hasCommand(BackupDatabaseCommand::class)
        ;
    }
}
