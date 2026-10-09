<?php

namespace SalvatoreCervone\BackupDatabase\Contracts;

interface BackupDriver
{
    /**
     * Esegue il backup del database.
     */
    public function backup(array $config): array;

    /**
     * Esegue il ripristino del database.
     *
     * @param  string  $backupFilePath  Percorso completo del file di backup
     */
    public function restore(array $config, string $backupFilePath): array;
}
