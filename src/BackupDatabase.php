<?php

namespace Salvatore\BackupDatabase;

use Carbon\Carbon;

class BackupDatabase
{
    public function __construct()
    {
        // Constructor code here
    }

    public function backup()
    {
        // "BACKUP DATABASE SQLTestDB TO DISK = 'c:\tmp\SQLTestDB.bak'   WITH FORMAT,      MEDIANAME = 'SQLServerBackups',      NAME = 'Full Backup of SQLTestDB';"
        $listconnections = config('backup-database.listconnection');
        foreach ($listconnections as $connection) {
            $connection = config("backup-database.connection");
            $driver = config("database.connections.{$connection}.driver");

            if ($driver == 'sqlsrv') {
                $dbhost = config('backup-database.dbhost');
                $dbname = config('backup-database.dbname');
                $daily = config('backup-database.daily');
                $destinationpath = config('backup-database.destinationpath');
                $name = $dbname  .  ($daily ? "_" . Carbon::now()->format('Y-m-d') : "") . ".bak";
                $user = env('DB_USERNAME');
                $password = env('DB_PASSWORD');
                $script = "BACKUP DATABASE " . $dbname . " TO DISK= '" . $destinationpath . $name . "'";
                $result = shell_exec('sqlcmd -S ' . $dbhost . ' -U ' .  $user . ' -P ' . $password . ' -Q "' . $script . '"');
                return $result;
            }
            throw new \Exception("Unsupported database driver: {$driver}");
        }
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
