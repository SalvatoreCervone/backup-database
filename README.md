# LARAVEL BACKUP-DATABASE

![laravel backup database](https://github.com/SalvatoreCervone/backup-database/blob/main/images/backup-database.jpeg)

## BETA FOR BACKUP DATABASE
> [!IMPORTANT]
> Actually V1 used for only backup MSSQL Database with Driver Sqlsrv

- [x] MSSQL
- [ ] MySQL
- [ ] MongoDB

## 1 or more Database

You can backup one or more database in single operation.
In config file you add connection name of you single or multi database and send backup.
Example of single:

```
 'listconnections' => [
        [
            'connection' => env('DB_CONNECTION'),            
            'daily' => false,
            'datetimeFormat' => 'Y-m-d H:i',
            'destinationpath' => 'c:\tmp\\',
            'days_for_delete' => 1,
            'soft_delete' => false
        ]
    ],
```
The backup config, for default, read DB_CONNECTION  of your env file.
From you connection name read any other paramters for execute backup
If, example, user in env file not have permission for backup db you setting manually user and password in config file.
example:
```
 'listconnections' => [
        [
            'connection' => env('DB_CONNECTION'),
            'db_name' => 'db_name',
            'db_host' => 'db_host',
            'db_username' => 'user',
            'db_password' => 'password123',
            'daily' => false,
            'datetimeFormat' => 'Y-m-d H:i',
            'destinationpath' => 'c:\tmp\\',
            'days_for_delete' => 1,
            'soft_delete' => false
        ]
    ],
```
## Delete

You can set, in config file, days_for_delete parameter.
In this Parameter you set the days for delete previus databases.
If you set days_for_delete = null delete is disabled.
If you set days_for_delete = 0 you delete all previus databases.

Another parameters in config file is: soft_delete
if you set this parameter to true you previus databases not would deleted but moved in folder called trash 
created in destinationpath

exemple:
```
//  config/backup-database.php
[
    'connection' => env('DB_CONNECTION'),
    'db_name' => 'db_name',
    'db_host' => 'db_host',
    'db_username' => 'user',
    'db_password' => 'password123',
    'daily' => false,
    'datetimeFormat' => 'Y-m-d H:i',
    'destinationpath' => 'c:\tmp\\',
    'days_for_delete' => 1,
    'soft_delete' => false
]
```
in this case trash folder would created in c:\tmp -> c:\tmp\trash

## For install:
```
composer require salvatorecervone/backup-database
```

## For publish config 
```
php artisan vendor:publish --tag="backup-database-config"
```

## For test use 

```
php artisan tink
```
after
```
BackupDatabase::backup();
```

This return result in terminal and create backup in config.destinationpath

## View

In the blade view 
```
http://127.0.0.1:8000/backups
```
 you have a list of your backup of your driver connection.

In this view you have information of backup and :
one button for delete a single backup
one button for lunch all backups

## Artisan command

You find a command artisan for lunch backup for CLI or insert in schedulate
```
php artisan backup-database:backup
```

## Schedule

If you schedule this, for example, every day you can use default laravel schedulate
```
App\Console\Kernel.php

 protected function schedule(Schedule $schedule)    {
    $schedule->command('backup-database:backup')->dailyAt('3:00')
}

```





