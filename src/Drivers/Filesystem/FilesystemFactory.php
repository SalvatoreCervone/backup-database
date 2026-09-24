<?php

namespace SalvatoreCervone\BackupDatabase\Drivers\Filesystem;

use SalvatoreCervone\BackupDatabase\Contracts\FilesystemDriver;

class FilesystemFactory
{
    public static function make(string $path): FilesystemDriver
    {
        // Se siamo su Windows, usiamo sempre il NativeDriver (PHP gestisce i percorsi UNC \\ nativamente)
        if (PHP_OS_FAMILY === 'Windows') {
            return new NativeDriver();
        }

        // Se siamo su Linux/altro
        // Controlliamo se il percorso sembra uno share SMB (inizia con // o smb:)
        if (str_starts_with($path, '//') || str_starts_with($path, 'smb:')) {
            return new LinuxSmbDriver();
        }

        // Altrimenti è un percorso locale su Linux
        return new NativeDriver();
    }
}
