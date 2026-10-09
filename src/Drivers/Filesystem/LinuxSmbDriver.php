<?php

namespace SalvatoreCervone\BackupDatabase\Drivers\Filesystem;

use Illuminate\Support\Facades\Process;
use SalvatoreCervone\BackupDatabase\Contracts\FilesystemDriver;
use SalvatoreCervone\BackupDatabase\Exceptions\SmbException;

class LinuxSmbDriver implements FilesystemDriver
{
    protected string $user;

    protected string $password;

    protected string $binPath;

    public function __construct()
    {
        $this->user = (string) config('backup-database.smb_user', '');
        $this->password = (string) config('backup-database.smb_password', '');
        $this->binPath = (string) config('backup-database.bin_paths.smbclient', 'smbclient');
    }

    public function listFiles(string $path, string $filter = '*'): array
    {
        $details = $this->parseSmbPath($path);
        if (! $details) {
            return [];
        }

        $cdCommand = ! empty($details['remainingPath'])
            ? 'cd "'.str_replace('/', '\\', $details['remainingPath']).'"; '
            : '';

        $smbCmd = "{$cdCommand}ls";

        $result = Process::run([
            $this->binPath,
            "//{$details['host']}/{$details['share']}",
            '-U', "{$this->user}%{$this->password}",
            '-c', $smbCmd,
        ]);

        if ($result->failed()) {
            $error = trim($result->errorOutput() ?: $result->output());
            throw new SmbException('SMB ls fallito: '.$error);
        }

        return $this->parseLsOutput($result->output(), $path, $filter);
    }

    public function deleteFile(string $path): bool
    {
        $details = $this->parseSmbPath($path);
        if (! $details) {
            return false;
        }

        $winPath = str_replace('/', '\\', $details['remainingPath']);

        $result = Process::run([
            $this->binPath,
            "//{$details['host']}/{$details['share']}",
            '-U', "{$this->user}%{$this->password}",
            '-c', "del \"{$winPath}\"",
        ]);

        if ($result->successful()) {
            return true;
        }

        return false;
    }

    public function moveFile(string $source, string $target): bool
    {
        $details = $this->parseSmbPath($source);
        $targetDetails = $this->parseSmbPath($target);
        if (! $details || ! $targetDetails) {
            return false;
        }

        $sourceWinPath = str_replace('/', '\\', $details['remainingPath']);
        $targetWinPath = str_replace('/', '\\', $targetDetails['remainingPath']);

        $targetDir = dirname($targetWinPath);
        $cmd = "mkdir \"{$targetDir}\"; rename \"{$sourceWinPath}\" \"{$targetWinPath}\"";

        $result = Process::run([
            $this->binPath,
            "//{$details['host']}/{$details['share']}",
            '-U', "{$this->user}%{$this->password}",
            '-c', $cmd,
        ]);

        return $result->successful() || str_contains($result->errorOutput(), 'NT_STATUS_OBJECT_NAME_COLLISION');
    }

    protected function parseSmbPath(string $path): ?array
    {
        $normalized = str_replace(['smb:', '\\\\', '\\', '//'], '/', $path);
        $clean = trim($normalized, '/');

        $parts = array_values(array_filter(explode('/', $clean), fn ($p) => $p !== ''));
        if (count($parts) < 2) {
            return null;
        }

        $host = array_shift($parts);
        $share = array_shift($parts);
        $remainingPath = implode('/', $parts);

        return [
            'host' => $host,
            'share' => $share,
            'remainingPath' => $remainingPath,
        ];
    }

    protected function parseLsOutput(string $output, string $basePath, string $filter = '*'): array
    {
        $list = [];
        $lines = explode("\n", $output);
        $normalizedBase = rtrim(str_replace('\\', '/', $basePath), '/').'/';

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_contains($line, 'blocks available')) {
                continue;
            }

            if (preg_match('/^\s*(.*?)\s+([A-Z]*)\s+(\d+)\s+(.*)$/', $line, $matches)) {
                $fileName = trim($matches[1]);
                if ($fileName === '.' || $fileName === '..') {
                    continue;
                }

                if ($filter !== '*' && ! fnmatch($filter, $fileName)) {
                    continue;
                }

                $list[] = [
                    'name' => $fileName,
                    'size' => (int) $matches[3],
                    'modified' => trim($matches[4]),
                    'destination' => $normalizedBase,
                    'full_path' => $normalizedBase.$fileName,
                ];
            }
        }

        return $list;
    }
}
