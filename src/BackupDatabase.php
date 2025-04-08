<?php

namespace Salvatore\BackupDatabase;

use PSpell\Config;

class BackupDatabase
{
    public function __construct()
    {
        // Constructor code here
    }

    public function backup()
    {
        // "BACKUP DATABASE SQLTestDB TO DISK = 'c:\tmp\SQLTestDB.bak'   WITH FORMAT,      MEDIANAME = 'SQLServerBackups',      NAME = 'Full Backup of SQLTestDB';"
        $dbname = Config::get('dbname');
        $dbhost = Config::get('dbhost');
        $daily = Config::get('daily');
        $destinationpath = Config::get('destinationpath');
        $name = $dbname  .  ($daily ? "_" . Carbon::now()->format('Y-m-d') : "") . ".bak";

        return  "BACKUP DATABASE " . $dbname . " TO DISK = '" . $destinationpath . $name . "'   WITH FORMAT,   NAME = '" . $name . "';";
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
