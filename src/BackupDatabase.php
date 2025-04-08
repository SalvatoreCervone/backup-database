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
        $listconnections = config('backup-database.listconnections');
        foreach ($listconnections as $connection) {
            $connectionDatabase = $connection['connection'];

            $driver = config("database.connections.{$connectionDatabase}.driver");

            if ($driver == 'sqlsrv') {
                $dbhost = $connection['db_host'] ?? config("database.connections.{$connectionDatabase}.host");
                $dbname = $connection['db_name'] ?? config("database.connections.{$connectionDatabase}.database");
                $username = $connection['db_username'] ?? config("database.connections.{$connectionDatabase}.username");
                $password = $connection['db_password'] ?? config("database.connections.{$connectionDatabase}.password");
                $daily = $connection['daily'];
                $destinationpath = $connection['destinationpath'];
                $name = $dbname  .  ($daily ? "_" . Carbon::now()->format($connection['datetimeFormat']) : "") . ".bak";
                $script = "BACKUP DATABASE " . $dbname . " TO DISK= '" . $destinationpath . $name . "'";
                $result = shell_exec('sqlcmd -S ' . $dbhost . ' -U ' .  $username . ' -P ' . $password . ' -Q "' . $script . '"');
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
