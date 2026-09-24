<?php

namespace SalvatoreCervone\BackupDatabase\Contracts;

interface FilesystemDriver
{
    /**
     * Elenca i file in un percorso.
     *
     * @param string $path
     * @param string $filter
     * @return array
     */
    public function listFiles(string $path, string $filter = '*'): array;

    /**
     * Elimina un file.
     *
     * @param string $path
     * @return bool
     */
    public function deleteFile(string $path): bool;

    /**
     * Sposta o rinomina un file.
     *
     * @param string $source
     * @param string $target
     * @return bool
     */
    public function moveFile(string $source, string $target): bool;
}
