<?php

namespace SalvatoreCervone\BackupDatabase\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \SalvatoreCervone\BackupDatabase\BackupDatabase
 */
class BackupDatabase extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \SalvatoreCervone\BackupDatabase\BackupDatabase::class;
    }
}
