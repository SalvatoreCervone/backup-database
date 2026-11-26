<?php

namespace SalvatoreCervone\BackupDatabase\Drivers\Filesystem;

use Illuminate\Support\Facades\Log;
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
        $configuredBin = (string) config('backup-database.bin_paths.smbclient', 'smbclient');
        $this->binPath = $this->resolveBinPath($configuredBin);
    }

    protected function resolveBinPath(string $configured): string
    {
        $configured = trim($configured);

        if ($configured !== '' && $configured !== 'smbclient') {
            return $configured;
        }

        foreach (['/usr/bin/smbclient', '/usr/local/bin/smbclient', '/bin/smbclient'] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return 'smbclient';
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
        if (! $this->isSafeSmbArgument($winPath)) {
            Log::warning("LinuxSmbDriver: percorso SMB non sicuro rifiutato in deleteFile: {$winPath}");

            return false;
        }

        $result = Process::run([
            $this->binPath,
            "//{$details['host']}/{$details['share']}",
            '-U', "{$this->user}%{$this->password}",
            '-c', "del \"{$winPath}\"",
        ]);

        if ($result->successful()) {
            return true;
        }

        Log::warning("LinuxSmbDriver: del fallito per {$winPath}: ".trim($result->errorOutput() ?: $result->output()));

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
        if (! $this->isSafeSmbArgument($sourceWinPath) || ! $this->isSafeSmbArgument($targetWinPath)) {
            Log::warning('LinuxSmbDriver: percorso SMB non sicuro rifiutato in moveFile.');

            return false;
        }

        // Compute parent directory on the normalized forward-slash path before converting to backslashes
        // (PHP's dirname() on Linux only splits on '/', not '\').
        $targetDirUnix = dirname($targetDetails['remainingPath']);
        if ($targetDirUnix !== '.' && $targetDirUnix !== '' && $targetDirUnix !== '/') {
            $targetDirWin = str_replace('/', '\\', $targetDirUnix);

            $mkdirResult = Process::run([
                $this->binPath,
                "//{$details['host']}/{$details['share']}",
                '-U', "{$this->user}%{$this->password}",
                '-c', "mkdir \"{$targetDirWin}\"",
            ]);

            $mkdirOutput = trim($mkdirResult->errorOutput().' '.$mkdirResult->output());
            if ($mkdirResult->failed() && ! str_contains($mkdirOutput, 'NT_STATUS_OBJECT_NAME_COLLISION')) {
                Log::warning("LinuxSmbDriver: mkdir fallito per {$targetDirWin}: {$mkdirOutput}");
            }
        }

        $renameResult = Process::run([
            $this->binPath,
            "//{$details['host']}/{$details['share']}",
            '-U', "{$this->user}%{$this->password}",
            '-c', "rename \"{$sourceWinPath}\" \"{$targetWinPath}\"",
        ]);

        if (! $renameResult->successful()) {
            $renameOutput = trim($renameResult->errorOutput().' '.$renameResult->output());
            Log::warning("LinuxSmbDriver: rename fallito da {$sourceWinPath} a {$targetWinPath}: {$renameOutput}");

            return false;
        }

        return true;
    }

    protected function isSafeSmbArgument(string $value): bool
    {
        return $value !== '' && ! preg_match('/["*;?!\x00-\x1F\x7F]/', $value);
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
                $attributes = $matches[2];
                if ($fileName === '.' || $fileName === '..' || str_contains($attributes, 'D')) {
                    continue;
                }

                if ($filter !== '*' && ! fnmatch($filter, $fileName, FNM_CASEFOLD)) {
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
