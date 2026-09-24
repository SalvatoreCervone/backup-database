<?php

namespace SalvatoreCervone\BackupDatabase;

use SalvatoreCervone\BackupDatabase\Contracts\BackupDriver;
use SalvatoreCervone\BackupDatabase\Drivers\SqlSrvDriver;
use SalvatoreCervone\BackupDatabase\Drivers\MySqlDriver;
use Exception;

class DriverManager
{
    public static function make(string $driver): BackupDriver
    {
        return match ($driver) {
            'sqlsrv' => new SqlSrvDriver(),
            'mysql' => new MySqlDriver(),
            default => throw new Exception("Unsupported database driver: {$driver}"),
        };
    }
}
