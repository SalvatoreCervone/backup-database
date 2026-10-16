<?php

namespace SalvatoreCervone\BackupDatabase\Drivers\Filesystem;

use Illuminate\Support\Facades\File;
use SalvatoreCervone\BackupDatabase\Contracts\FilesystemDriver;

class NativeDriver implements FilesystemDriver
{
    public function listFiles(string $path, string $filter = '*'): array
    {
        $list = [];
        $normalizedPath = rtrim($path, '/\\').DIRECTORY_SEPARATOR;

        // scandir is much more reliable than glob() on Windows UNC network paths (\\server\share\...)
        if (is_dir($normalizedPath)) {
            $entries = @scandir($normalizedPath);
            if ($entries !== false) {
                foreach ($entries as $entry) {
                    if ($entry === '.' || $entry === '..') {
                        continue;
                    }

                    // Case-insensitive matching for extensions (e.g. *.bak, *.sql, *.BAK)
                    if ($filter !== '*' && ! fnmatch($filter, $entry, FNM_CASEFOLD)) {
                        continue;
                    }

                    $fullPath = $normalizedPath.$entry;
                    if (is_file($fullPath)) {
                        $list[] = [
                            'name' => $entry,
                            'size' => @filesize($fullPath) ?: 0,
                            'modified' => @filemtime($fullPath) ? date('D M d H:i:s Y', filemtime($fullPath)) : 'N/A',
                            'destination' => $normalizedPath,
                            'full_path' => $fullPath,
                        ];
                    }
                }

                return $list;
            }
        }

        // Fallback to File::glob if scandir could not open directory
        $files = File::glob($normalizedPath.$filter) ?: [];
        foreach ($files as $file) {
            $list[] = [
                'name' => basename($file),
                'size' => @File::size($file) ?: 0,
                'modified' => @File::lastModified($file) ? date('D M d H:i:s Y', File::lastModified($file)) : 'N/A',
                'destination' => dirname($file).DIRECTORY_SEPARATOR,
                'full_path' => $file,
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
        if (! File::isDirectory($targetDir)) {
            File::makeDirectory($targetDir, 0777, true);
        }

        if (File::exists($source)) {
            return File::move($source, $target);
        }

        return false;
    }
}
