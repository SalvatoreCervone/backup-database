<?php

namespace SalvatoreCervone\BackupDatabase\Contracts;

interface BackupDriver
{
    /**
     * Esegue il backup del database.
     *
     * @param array $config
     * @return array
     */
    public function backup(array $config): array;

    /**
     * Esegue il ripristino del database.
     *
     * @param array $config
     * @param string $backupFilePath Percorso completo del file di backup
     * @return array
     */
    public function restore(array $config, string $backupFilePath): array;
}
