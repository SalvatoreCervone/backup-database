<?php

namespace SalvatoreCervone\BackupDatabase\Drivers\Filesystem;

use SalvatoreCervone\BackupDatabase\Contracts\FilesystemDriver;
use Illuminate\Support\Facades\File;

class NativeDriver implements FilesystemDriver
{
    public function listFiles(string $path, string $filter = '*'): array
    {
        $files = File::glob($path . $filter);
        $list = [];

        foreach ($files as $file) {
            $list[] = [
                'name' => basename($file),
                'size' => File::size($file),
                'modified' => date("D M d H:i:s Y", File::lastModified($file)),
                'destination' => dirname($file) . DIRECTORY_SEPARATOR,
                'full_path' => $file
            ];
        }

        return $list;
    }

    public function deleteFile(string $path): bool
    {
        if (File::exists($path)) {
            return File::delete($path);
        }
        return false;
    }

    public function moveFile(string $source, string $target): bool
    {
        $targetDir = dirname($target);
        if (!File::isDirectory($targetDir)) {
            File::makeDirectory($targetDir, 0777, true);
        }

        if (File::exists($source)) {
            return File::move($source, $target);
        }
        return false;
    }
}
