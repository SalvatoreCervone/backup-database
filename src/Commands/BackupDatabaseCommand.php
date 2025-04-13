<?php

namespace SalvatoreCervone\BackupDatabase\Commands;

use Illuminate\Console\Command;
use SalvatoreCervone\BackupDatabase\Facades\BackupDatabase;

class BackupDatabaseCommand extends Command
{
    public $signature = 'backup-database:backup';

    public $description = 'Backup database';

    public function handle(): int
    {

        BackupDatabase::backup();
        $this->info('Database backup completed successfully.');

        return self::SUCCESS;
    }
}
