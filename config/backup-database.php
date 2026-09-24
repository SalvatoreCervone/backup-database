<?php

// config for salvatorecervone/BackupDatabase
return [

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the middleware and prefix for the backup dashboard routes.
    | By default, routes are protected by 'web' and 'auth' middleware.
    |
    */

    'middleware' => ['web', 'auth'],

    'route_prefix' => 'backups',

    /*
    |--------------------------------------------------------------------------
    | Authorization Gate
    |--------------------------------------------------------------------------
    |
    | If set, users must pass this Gate check to access backup operations.
    | Set to null to disable gate-based authorization (middleware still applies).
    |
    | Example: 'viewBackups' — then define the gate in your AuthServiceProvider.
    |
    */

    'authorization_gate' => null,

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Define one or more database connections to backup. Each entry references
    | a connection name from your config/database.php. You can optionally
    | override host, port, database name, username, and password per entry.
    |
    */

    'listconnections' => [
        [
            'connection' => env('DB_CONNECTION'),
            // 'db_name' => env('DB_DATABASE'),
            // 'db_host' => env('DB_HOST'),
            // 'db_username' => env('DB_USERNAME'),
            // 'db_password' => env('DB_PASSWORD'),
            'daily' => false,
            'datetimeFormat' => 'Y-m-d_H-i',
            'destinationpath' => 'c:\tmp\\',

            /**
             *  set null for not delete any previous database
             *  set 0 for delete all previous backup with start with the same dbname
             */
            'days_for_delete' => 1, //

            /**
             * if true, the backup will move to the trash folder in same directory
             * if false, the backup will be deleted
             */
            'soft_delete' => false,
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Binary Paths
    |--------------------------------------------------------------------------
    |
    | Specify the full path to external binaries if they are not in your
    | system's PATH. These are used by the database and filesystem drivers.
    |
    */

    'bin_paths' => [
        'sqlcmd' => env('BACKUP_SQLCMD_PATH', '/opt/mssql-tools18/bin/sqlcmd'),
        'mysqldump' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),
        'mysql' => env('BACKUP_MYSQL_PATH', 'mysql'),
        'smbclient' => env('BACKUP_SMBCLIENT_PATH', 'smbclient'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMB Credentials
    |--------------------------------------------------------------------------
    |
    | Credentials for accessing SMB/CIFS network shares on Linux.
    | On Windows, PHP uses the system's native UNC path handling.
    |
    */

    'smb_user' => env('BACKUP_SMB_USER'),
    'smb_password' => env('BACKUP_SMB_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Email Notifications
    |--------------------------------------------------------------------------
    |
    | Set an email address to receive notifications when a backup fails.
    | Requires your Laravel application to have SMTP/mail configured.
    |
    */

    'notification_email' => env('BACKUP_NOTIFICATION_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Enable logging of all operations (backup, restore, delete) to a
    | dedicated log file at: storage/logs/backup-database-YYYY-MM-DD.log
    |
    */

    'enable_logging' => env('BACKUP_LOGGING_ENABLED', true),
];
