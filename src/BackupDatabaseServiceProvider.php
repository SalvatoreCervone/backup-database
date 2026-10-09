<?php

namespace SalvatoreCervone\BackupDatabase;

use SalvatoreCervone\BackupDatabase\Commands\BackupDatabaseCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

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
            ->hasCommand(BackupDatabaseCommand::class);
    }
}
