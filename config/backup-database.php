<?php

// config for Salvatore/BackupDatabase
return [
    'listconnections' => [
        [
            'connection' => env('DB_CONNECTION'),
            'dbname' => env('DB_DATABASE'),
            'dbhost' => env('DB_HOST'),
            'db_username' => env('DB_USERNAME'),
            'db_password' => env('DB_PASSWORD'),
            'dbhost' => env('DB_HOST'),
            'daily' => false,
            'datetimeFormat' => 'Y-m-d H-i',
            'destinationpath' => 'c:\tmp\\',
        ]
    ],
    // 'base' => "BACKUP DATABASE SQLTestDB TO DISK = 'c:\tmp\SQLTestDB.bak'   WITH FORMAT,    NAME = 'Full Backup of SQLTestDB';"
];
