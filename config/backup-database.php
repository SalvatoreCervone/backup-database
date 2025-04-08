<?php

// config for Salvatore/BackupDatabase
return [
    'dbname' => env('DB_DATABASE'),
    'dbhost' => env('DB_HOST'),
    'daily' => false,
    'destinationpath' => 'c:\tmp\\',
    // 'base' => "BACKUP DATABASE SQLTestDB TO DISK = 'c:\tmp\SQLTestDB.bak'   WITH FORMAT,    NAME = 'Full Backup of SQLTestDB';"
];
