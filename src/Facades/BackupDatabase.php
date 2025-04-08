<?php

namespace Salvatore\BackupDatabase\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Salvatore\BackupDatabase\BackupDatabase
 */
class BackupDatabase extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Salvatore\BackupDatabase\BackupDatabase::class;
    }
}
