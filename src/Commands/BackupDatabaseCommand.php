<?php

namespace SalvatoreCervone\BackupDatabase\Commands;

use Illuminate\Console\Command;
use SalvatoreCervone\BackupDatabase\Facades\BackupDatabase;

class BackupDatabaseCommand extends Command
{
    public $signature = 'backup-database:backup';

    public $description = 'Run database backup for all configured connections';

    public function handle(): int
    {
        $this->info('Starting database backup...');

        $results = BackupDatabase::backup();

        $hasErrors = false;

        foreach ($results as $result) {
            if ($result['status'] ?? false) {
                $message = $result['message'] ?? 'OK';
                $file = $result['file'] ?? null;
                $this->info("✅ {$message}" . ($file ? " [{$file}]" : ''));
            } else {
                $hasErrors = true;
                $this->error("❌ " . ($result['message'] ?? 'Unknown error'));
            }
        }

        if ($hasErrors) {
            $this->warn('Backup completed with errors. Check the logs for details.');
            return self::FAILURE;
        }

        $this->info('All database backups completed successfully.');

        return self::SUCCESS;
    }
}
