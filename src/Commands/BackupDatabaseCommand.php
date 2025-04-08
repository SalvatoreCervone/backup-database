<?php

namespace Salvatore\BackupDatabase\Commands;

use Illuminate\Console\Command;

class BackupDatabaseCommand extends Command
{
    public $signature = 'backup-database';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
