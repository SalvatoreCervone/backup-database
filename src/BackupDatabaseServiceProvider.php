<?php

namespace Salvatore\BackupDatabase;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Salvatore\BackupDatabase\Commands\BackupDatabaseCommand;

class BackupDatabaseServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('backup-database')
            ->hasConfigFile()
            // ->hasViews()
            // ->hasMigration('create_backup_database_table')
            // ->hasCommand(BackupDatabaseCommand::class)
        ;
    }
}
