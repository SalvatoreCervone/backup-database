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
                return $this->backupMssql($connection);
            }
            throw new \Exception("Unsupported database driver: {$driver}");
        }
    }

    private function backupMssql($connection)
    {
        $dbhost = $connection['dbhost'];
        $dbname = $connection['dbname'];
        $daily = $connection['daily'];
        $destinationpath = $connection['destinationpath'];
        $name = $dbname  .  ($daily ? "_" . Carbon::now()->format($connection['datetimeFormat']) : "") . ".bak";
        $user = $connection['db_username'];
        $password = $connection['db_password'];
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
