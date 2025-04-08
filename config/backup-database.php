<?php

// config for Salvatore/BackupDatabase
return [
    'listconnections' => [
        [
            'connection' => env('DB_CONNECTION'),
            // 'db_name' => env('DB_DATABASE'),
            // 'db_host' => env('DB_HOST'),
            // 'db_username' => env('DB_USERNAME'),
            // 'db_password' => env('DB_PASSWORD'),
            'daily' => false,
            'datetimeFormat' => 'Y-m-d H:i',
            'destinationpath' => 'c:\tmp\\',
        ]
    ],
    // 'base' => "BACKUP DATABASE SQLTestDB TO DISK = 'c:\tmp\SQLTestDB.bak'   WITH FORMAT,    NAME = 'Full Backup of SQLTestDB';"
];
