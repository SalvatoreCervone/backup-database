<?php

namespace Salvatore\BackupDatabase;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;



class BackupDatabase
{
    public function __construct()
    {
        // Constructor code here
    }

    public function backup()
    {
        // "BACKUP DATABASE SQLTestDB TO DISK = 'c:\tmp\SQLTestDB.bak'   WITH FORMAT,      MEDIANAME = 'SQLServerBackups',      NAME = 'Full Backup of SQLTestDB';"
        $dbname = Config::get('backup-database.dbname');
        $dbhost = Config::get('backup-database.dbhost');
        $daily = Config::get('backup-database.daily');
        $destinationpath = Config::get('backup-database.destinationpath');
        $name = $dbname  .  ($daily ? "_" . Carbon::now()->format('Y-m-d') : "") . ".bak";
        $user = env('DB_USERNAME');
        $password = env('DB_PASSWORD');
        $script = "BACKUP DATABASE " . $dbname . " TO DISK= '" . $destinationpath . $name . "'";
        $result = shell_exec('sqlcmd -S ' . $dbhost . ' -U ' .  $user . ' -P ' . $password . ' -Q "' . $script . '"');
        return $result;
    }

    public function restore()
    {
        // Restore logic here
    }

    public function getStatus()
    {
        // Get status logic here
    }

    public function setConfig($config)
    {
        // Set configuration logic here
    }

    public function getConfig()
    {
        // Get configuration logic here
    }

    public function validateConfig($config)
    {
        // Validate configuration logic here
    }

    public function log($message)
    {
        // Log message logic here
    }
}
