<?php

namespace SalvatoreCervone\BackupDatabase;

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
        $result = null;
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
                $resultCreateFolder = $this->createFolder($destinationpath);
                if ($resultCreateFolder['status'] == false) {
                    $result[] = $resultCreateFolder['error'];
                    continue;
                }
                $name = $dbname  .  ($daily ? "_" . Carbon::now()->format($connection['datetimeFormat']) : "") . ".bak";
                $script = "BACKUP DATABASE " . $dbname . " TO DISK= '" . $destinationpath . $name . "'";
                $result[] = shell_exec('sqlcmd -S ' . $dbhost . ' -U ' .  $username . ' -P ' . $password . ' -Q "' . $script . '"');
            } else {

                $result[] = "Unsupported database driver: {$driver}";
            }

        }
        return $result;
    }

    public function restore()
    {
        // Restore logic here
    }

    public function getStatus()
    {
        $listBackups = null;
        $listconnections = config('backup-database.listconnections');
        foreach ($listconnections as $connection) {
            $connectionDatabase = $connection['connection'];
            $listBackups[$connectionDatabase] = null;
            $driver = config("database.connections.{$connectionDatabase}.driver");
            if ($driver == 'sqlsrv') {
                $destinationpath = $connection['destinationpath'];
                foreach (glob($destinationpath . "*.bak") as $file) {
                    $listBackups[$connectionDatabase][] = [
                        'name' => basename($file),
                        'size' => filesize($file),
                        'modified' => date("F d Y H:i:s.", filemtime($file)),
                        'destination' => $destinationpath,
                    ];
                }
            }
        }
        return $listBackups;
    }
    public function delete()
    {
        $file = request()->input('file');

        if (file_exists($file)) {
            unlink($file);
            return true;
        }
        return false;
    }


    public function log($message)
    {
        // Log message logic here
    }

    private function createFolder($destinationpath)
    {
        if (!is_dir($destinationpath)) {
            mkdir($destinationpath, 0777, true);
        }
        if (!is_writable($destinationpath)) {
            return ['status' => false, 'error' => "Destination path is not writable: {$destinationpath}"];
        }
        return ['status' => true];
    }
}
