# BACKUP-DATABASE

## BETA FOR BACKUP DATABASE

Actually V1 used for only backup MSSQL Database with Driver Sqlsrv

## 1 or more Database

You would backup one or more database in single operation.
In config file you add connection name of you single or multi database and send backup
example of single:
```
 'listconnections' => [
        [
            'connection' => env('DB_CONNECTION'),            
            'daily' => false,
            'datetimeFormat' => 'Y-m-d H:i',
            'destinationpath' => 'c:\tmp\\',
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
        ]
    ],
```

## For install:

```
composer require salvatore/backup-database
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

## RoadMap

### create table for store list of backups
### create view for list stored backups




