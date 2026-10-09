<?php

namespace SalvatoreCervone\BackupDatabase;

use Exception;
use SalvatoreCervone\BackupDatabase\Contracts\BackupDriver;
use SalvatoreCervone\BackupDatabase\Drivers\MySqlDriver;
use SalvatoreCervone\BackupDatabase\Drivers\SqlSrvDriver;

class DriverManager
{
    public static function make(string $driver): BackupDriver
    {
        return match ($driver) {
            'sqlsrv' => new SqlSrvDriver,
            'mysql' => new MySqlDriver,
            default => throw new Exception("Unsupported database driver: {$driver}"),
        };
    }
}
