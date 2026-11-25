<?php

namespace SalvatoreCervone\BackupDatabase;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use SalvatoreCervone\BackupDatabase\Drivers\Filesystem\FilesystemFactory;
use SalvatoreCervone\BackupDatabase\Exceptions\BackupException;
use SalvatoreCervone\BackupDatabase\Security\PathValidator;
use SalvatoreCervone\BackupDatabase\Support\BackupNotifier;
use SalvatoreCervone\BackupDatabase\Support\EncodingHelper;

class BackupDatabase
{
    public array $supportedDrivers = ['sqlsrv', 'mysql'];

    protected BackupNotifier $notifier;

    public function __construct(?BackupNotifier $notifier = null)
    {
        $this->notifier = $notifier ?? new BackupNotifier;
    }

    /**
     * Run backup for all configured connections.
     *
     * @return array List of result arrays with 'status', 'message', and optional 'file' keys
     */
    public function backup(): array
    {
        $results = [];
        $connectionResults = [];
        $allCleanupResults = [];
        $hasFailures = false;
        $listconnections = config('backup-database.listconnections', []);

        foreach ($listconnections as $connection) {
            $connectionName = $connection['connection'] ?? 'Unknown';
            try {
                $driverName = config("database.connections.{$connectionName}.driver");

                Log::info("BackupDatabase: Avvio backup per {$connectionName} ({$driverName})");

                $driver = DriverManager::make($driverName);

                $dbConfig = [
                    'db_host' => $connection['db_host'] ?? config("database.connections.{$connectionName}.host"),
                    'db_port' => $connection['db_port'] ?? config("database.connections.{$connectionName}.port"),
                    'db_name' => $connection['db_name'] ?? config("database.connections.{$connectionName}.database"),
                    'db_username' => $connection['db_username'] ?? config("database.connections.{$connectionName}.username"),
                    'db_password' => $connection['db_password'] ?? config("database.connections.{$connectionName}.password"),
                    'destinationpath' => $this->normalizePath($connection['destinationpath']),
                    'daily' => $connection['daily'] ?? false,
                    'datetimeFormat' => $connection['datetimeFormat'] ?? 'Y-m-d_H-i',
                ];

                $this->ensureDirectoryExists($dbConfig['destinationpath']);

                $backupResult = $driver->backup($dbConfig);
                $results[] = $backupResult;

                if ($backupResult['status']) {
                    $file = $backupResult['file'] ?? null;
                    $message = EncodingHelper::toUtf8((string) ($backupResult['message'] ?? 'Backup completato'));
                    $this->logOperation('BACKUP SUCCESS', "Connessione: {$connectionName} - File: {$file}");

                    $connectionResults[] = [
                        'connection' => $connectionName,
                        'status' => true,
                        'file' => $file,
                        'message' => $message,
                    ];

                    $cleanupResults = $this->cleanupOldBackups($connection, $dbConfig, $file);
                    $allCleanupResults = array_merge($allCleanupResults, $cleanupResults);
                    $results = array_merge($results, $cleanupResults);
                } else {
                    $hasFailures = true;
                    $errorMsg = EncodingHelper::toUtf8((string) ($backupResult['message'] ?? 'Unknown error'));
                    $this->logOperation('BACKUP FAILED', "Connessione: {$connectionName} - Errore: ".$errorMsg);
                    $this->notifyFailure($connectionName, $errorMsg);

                    $connectionResults[] = [
                        'connection' => $connectionName,
                        'status' => false,
                        'file' => $backupResult['file'] ?? null,
                        'message' => $errorMsg,
                    ];
                }

            } catch (\Exception $e) {
                $hasFailures = true;
                $errorMsg = EncodingHelper::toUtf8($e->getMessage());
                Log::error("BackupDatabase Error [{$connectionName}]: ".$errorMsg);
                $this->logOperation('BACKUP ERROR', "Connessione: {$connectionName} - Exception: ".$errorMsg);

                $this->notifyFailure($connectionName, $errorMsg);

                $connectionResults[] = [
                    'connection' => $connectionName,
                    'status' => false,
                    'file' => null,
                    'message' => $errorMsg,
                ];

                $results[] = ['status' => false, 'message' => "{$connectionName}: ".$errorMsg];
            }
        }

        if (! empty($connectionResults)) {
            if (! $hasFailures) {
                $this->notifier->notifySuccess($connectionResults);
            }

            $this->notifier->notifySummary($connectionResults, $allCleanupResults);
        }

        return EncodingHelper::toUtf8($results);
    }

    /**
     * Restore a database from a backup file.
     *
     * @param  string  $connectionName  The connection name from config
     * @param  string  $fileName  The backup file name (basename only, validated server-side)
     * @return array Result with 'status' and 'message' keys
     */
    public function restore(string $connectionName, string $fileName): array
    {
        try {
            // Validate and resolve the full file path securely
            $fullPath = PathValidator::validateFileName($fileName, $connectionName);

            $listconnections = config('backup-database.listconnections', []);
            $connection = collect($listconnections)->firstWhere('connection', $connectionName);

            if (! $connection) {
                throw new BackupException("Configurazione connessione {$connectionName} non trovata.");
            }

            $driverName = config("database.connections.{$connectionName}.driver");
            $driver = DriverManager::make($driverName);

            $dbConfig = [
                'db_host' => $connection['db_host'] ?? config("database.connections.{$connectionName}.host"),
                'db_port' => $connection['db_port'] ?? config("database.connections.{$connectionName}.port"),
                'db_name' => $connection['db_name'] ?? config("database.connections.{$connectionName}.database"),
                'db_username' => $connection['db_username'] ?? config("database.connections.{$connectionName}.username"),
                'db_password' => $connection['db_password'] ?? config("database.connections.{$connectionName}.password"),
            ];

            $result = $driver->restore($dbConfig, $fullPath);

            $this->logOperation('RESTORE SUCCESS', "Connessione: {$connectionName} - File: {$fileName}");

            return [
                'status' => true,
                'message' => $result['message'],
            ];

        } catch (\Exception $e) {
            $errorMsg = EncodingHelper::toUtf8($e->getMessage());
            Log::error("Restore Error [{$connectionName}]: ".$errorMsg);
            $this->logOperation('RESTORE FAILED', "Connessione: {$connectionName} - File: {$fileName} - Errore: ".$errorMsg);

            return [
                'status' => false,
                'message' => 'Ripristino fallito: '.$errorMsg,
            ];
        }
    }

    /**
     * Get the daily activity logs.
     */
    public function getLogs(): string
    {
        $logPath = storage_path('logs/backup-database-'.date('Y-m-d').'.log');
        if (File::exists($logPath)) {
            return EncodingHelper::toUtf8(File::get($logPath));
        }

        return 'Nessun log disponibile per oggi.';
    }

    /**
     * Get the status of all backups across configured connections.
     *
     * @return array Associative array keyed by connection name, containing backup file lists
     */
    public function getStatus(): array
    {
        $listGlobalFile = [];
        $listconnections = config('backup-database.listconnections', []);

        $uniquePaths = collect($listconnections)->map(function ($item) {
            return [
                'path' => $this->normalizePath($item['destinationpath']),
                'connection' => $item['connection'],
            ];
        })->unique('path');

        foreach ($uniquePaths as $pathInfo) {
            try {
                $fs = FilesystemFactory::make($pathInfo['path']);
                $backups = $fs->listFiles($pathInfo['path'], '*.bak');
                $backups = array_merge($backups, $fs->listFiles($pathInfo['path'], '*.sql'));
                $listGlobalFile[$pathInfo['connection']] = $backups;
            } catch (\Exception $e) {
                Log::warning("Impossibile leggere file in {$pathInfo['path']}: ".$e->getMessage());
                $listGlobalFile[$pathInfo['connection']] = [];
            }
        }

        return EncodingHelper::toUtf8($listGlobalFile);
    }

    /**
     * Delete a backup file.
     *
     * @param  string  $connectionName  The connection name (used to resolve the allowed directory)
     * @param  string  $fileName  The backup file name (basename only)
     * @return array Result with 'status' and 'message' keys
     */
    public function delete(string $connectionName, string $fileName): array
    {
        try {
            // Validate and resolve the full file path securely
            $fullPath = PathValidator::validateFileName($fileName, $connectionName);

            $listconnections = config('backup-database.listconnections', []);
            $connection = collect($listconnections)->firstWhere('connection', $connectionName);
            $softDelete = (bool) ($connection['soft_delete'] ?? false);

            $fs = FilesystemFactory::make($fullPath);

            if ($softDelete) {
                $basePath = $this->normalizePath($connection['destinationpath']);
                $trashPath = $basePath.'trash'.DIRECTORY_SEPARATOR.date('Ymd_His').'_'.$fileName;
                $status = $fs->moveFile($fullPath, $trashPath);
                $this->logOperation('SOFT DELETE '.($status ? 'SUCCESS' : 'FAILED'), "Connessione: {$connectionName} - File: {$fileName}");

                return [
                    'status' => $status,
                    'message' => $status
                        ? 'File spostato nel cestino (trash) correttamente.'
                        : 'Errore durante lo spostamento nel cestino (trash).',
                ];
            }

            $status = $fs->deleteFile($fullPath);
            $this->logOperation('DELETE '.($status ? 'SUCCESS' : 'FAILED'), "Connessione: {$connectionName} - File: {$fileName}");

            return [
                'status' => $status,
                'message' => $status ? 'File eliminato correttamente.' : "Errore durante l'eliminazione.",
            ];
        } catch (\Exception $e) {
            return [
                'status' => false,
                'message' => 'Errore: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Cleanup old backups based on retention policy (days_for_delete).
     */
    protected function cleanupOldBackups(array $connection, array $dbConfig, ?string $currentBackupFile = null): array
    {
        $days = $connection['days_for_delete'] ?? null;
        if ($days === null) {
            return [];
        }

        $days = (int) $days;
        $softDelete = (bool) ($connection['soft_delete'] ?? false);
        $dbname = $dbConfig['db_name'];
        $path = $dbConfig['destinationpath'];
        $connectionName = $connection['connection'] ?? $dbname;

        $fs = FilesystemFactory::make($path);
        $files = $fs->listFiles($path, $dbname.'*');
        $results = [];
        $cutoffDate = now()->subDays($days)->startOfDay();

        foreach ($files as $file) {
            // Never remove the backup file that was just created in this run
            if ($currentBackupFile !== null && strcasecmp($file['name'], $currentBackupFile) === 0) {
                continue;
            }

            $shouldRemove = false;

            if ($days === 0) {
                $shouldRemove = true;
            } elseif (preg_match('/(\d{4}-\d{2}-\d{2})/', $file['name'], $matches)) {
                $fileDate = Carbon::parse($matches[1])->startOfDay();
                $shouldRemove = $fileDate->lessThanOrEqualTo($cutoffDate);
            }

            if (! $shouldRemove) {
                continue;
            }

            if ($softDelete) {
                $trashPath = $path.'trash'.DIRECTORY_SEPARATOR.date('Ymd_His').'_'.$file['name'];
                $success = $fs->moveFile($file['full_path'], $trashPath);
                $action = 'Soft delete';
            } else {
                $success = $fs->deleteFile($file['full_path']);
                $action = 'Hard delete';
            }

            $this->logOperation(
                'CLEANUP '.($success ? 'SUCCESS' : 'FAILED'),
                "Connessione: {$connectionName} - {$action} per {$file['name']}"
            );

            if (! $success) {
                Log::warning("BackupDatabase Cleanup FAILED [{$connectionName}]: {$action} per {$file['name']}");
            }

            $results[] = ['status' => $success, 'message' => "{$action} per {$file['name']}"];
        }

        return $results;
    }

    /**
     * Send email notification on backup failure.
     */
    protected function notifyFailure(string $connectionName, string $message): void
    {
        $this->notifier->notifyFailure($connectionName, $message);
    }

    /**
     * Normalize a path to use consistent directory separators.
     */
    protected function normalizePath(string $path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        return rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
    }

    /**
     * Ensure a directory exists on the local filesystem.
     * Does not create directories on SMB/UNC paths (handled remotely or by the SMB driver).
     */
    protected function ensureDirectoryExists(string $path): void
    {
        if (str_starts_with($path, '//') || str_starts_with($path, '\\\\') || str_starts_with($path, 'smb:')) {
            return;
        }

        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

    /**
     * Write an entry to the package's daily log file.
     */
    protected function logOperation(string $type, string $message): void
    {
        if (! config('backup-database.enable_logging', true)) {
            return;
        }

        $logPath = storage_path('logs/backup-database-'.date('Y-m-d').'.log');
        $timestamp = date('Y-m-d H:i:s');
        $cleanMessage = EncodingHelper::toUtf8($message);
        $logEntry = "[{$timestamp}] [{$type}] {$cleanMessage}".PHP_EOL;

        try {
            File::append($logPath, $logEntry);
        } catch (\Exception $e) {
            Log::error('Impossibile scrivere nel log del pacchetto: '.$e->getMessage());
        }
    }
}
