<?php

namespace SalvatoreCervone\BackupDatabase\Contracts;

interface FilesystemDriver
{
    /**
     * Elenca i file in un percorso.
     */
    public function listFiles(string $path, string $filter = '*'): array;

    /**
     * Elimina un file.
     */
    public function deleteFile(string $path): bool;

    /**
     * Sposta o rinomina un file.
     */
    public function moveFile(string $source, string $target): bool;
}
