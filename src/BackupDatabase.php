<?php

namespace SalvatoreCervone\BackupDatabase;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\File;
use SalvatoreCervone\BackupDatabase\Mail\BackupFailedMail;
use SalvatoreCervone\BackupDatabase\Exceptions\BackupException;
use SalvatoreCervone\BackupDatabase\Drivers\Filesystem\FilesystemFactory;
use SalvatoreCervone\BackupDatabase\Security\PathValidator;

class BackupDatabase
{
    public array $supportedDrivers = ['sqlsrv', 'mysql'];

    public function __construct()
    {
    }

    /**
     * Run backup for all configured connections.
     *
     * @return array List of result arrays with 'status', 'message', and optional 'file' keys
     */
    public function backup(): array
    {
        $results = [];
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
                    $this->logOperation("BACKUP SUCCESS", "Connessione: {$connectionName} - File: {$backupResult['file']}");
                    $cleanupResults = $this->cleanupOldBackups($connection, $dbConfig);
                    $results = array_merge($results, $cleanupResults);
                } else {
                    $this->logOperation("BACKUP FAILED", "Connessione: {$connectionName} - Errore: " . ($backupResult['message'] ?? 'Unknown error'));
                }

            } catch (\Exception $e) {
                Log::error("BackupDatabase Error [{$connectionName}]: " . $e->getMessage());
                $this->logOperation("BACKUP ERROR", "Connessione: {$connectionName} - Exception: " . $e->getMessage());
                
                $this->notifyFailure($connectionName, $e->getMessage());

                $results[] = ['status' => false, 'message' => "{$connectionName}: " . $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Restore a database from a backup file.
     *
     * @param string $connectionName The connection name from config
     * @param string $fileName The backup file name (basename only, validated server-side)
     * @return array Result with 'status' and 'message' keys
     */
    public function restore(string $connectionName, string $fileName): array
    {
        try {
            // Validate and resolve the full file path securely
            $fullPath = PathValidator::validateFileName($fileName, $connectionName);

            $listconnections = config('backup-database.listconnections', []);
            $connection = collect($listconnections)->firstWhere('connection', $connectionName);

            if (!$connection) {
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

            $this->logOperation("RESTORE SUCCESS", "Connessione: {$connectionName} - File: {$fileName}");

            return [
                'status' => true,
                'message' => $result['message']
            ];

        } catch (\Exception $e) {
            Log::error("Restore Error [{$connectionName}]: " . $e->getMessage());
            $this->logOperation("RESTORE FAILED", "Connessione: {$connectionName} - File: {$fileName} - Errore: " . $e->getMessage());
            return [
                'status' => false,
                'message' => "Ripristino fallito: " . $e->getMessage()
            ];
        }
    }

    /**
     * Get the daily activity logs.
     */
    public function getLogs(): string
    {
        $logPath = storage_path('logs/backup-database-' . date('Y-m-d') . '.log');
        if (File::exists($logPath)) {
            return File::get($logPath);
        }
        return "Nessun log disponibile per oggi.";
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
                'connection' => $item['connection']
            ];
        })->unique('path');

        foreach ($uniquePaths as $pathInfo) {
            try {
                $fs = FilesystemFactory::make($pathInfo['path']);
                $backups = $fs->listFiles($pathInfo['path'], '*.bak');
                $backups = array_merge($backups, $fs->listFiles($pathInfo['path'], '*.sql'));
                $listGlobalFile[$pathInfo['connection']] = $backups;
            } catch (\Exception $e) {
                Log::warning("Impossibile leggere file in {$pathInfo['path']}: " . $e->getMessage());
                $listGlobalFile[$pathInfo['connection']] = [];
            }
        }

        return $listGlobalFile;
    }

    /**
     * Delete a backup file.
     *
     * @param string $connectionName The connection name (used to resolve the allowed directory)
     * @param string $fileName The backup file name (basename only)
     * @return array Result with 'status' and 'message' keys
     */
    public function delete(string $connectionName, string $fileName): array
    {
        try {
            // Validate and resolve the full file path securely
            $fullPath = PathValidator::validateFileName($fileName, $connectionName);

            $fs = FilesystemFactory::make($fullPath);
            $status = $fs->deleteFile($fullPath);
            $this->logOperation("DELETE " . ($status ? "SUCCESS" : "FAILED"), "File: {$fileName}");
            return [
                'status' => $status,
                'message' => $status ? "File eliminato correttamente." : "Errore durante l'eliminazione."
            ];
        } catch (\Exception $e) {
            return [
                'status' => false,
                'message' => "Errore: " . $e->getMessage()
            ];
        }
    }

    /**
     * Cleanup old backups based on retention policy (days_for_delete).
     */
    protected function cleanupOldBackups(array $connection, array $dbConfig): array
    {
        $days = $connection['days_for_delete'] ?? null;
        if ($days === null) return [];

        $softDelete = $connection['soft_delete'] ?? false;
        $dbname = $dbConfig['db_name'];
        $path = $dbConfig['destinationpath'];

        $fs = FilesystemFactory::make($path);
        $files = $fs->listFiles($path, $dbname . "*");
        $results = [];

        foreach ($files as $file) {
            if (preg_match('/(\d{4}-\d{2}-\d{2})/', $file['name'], $matches)) {
                $fileDate = Carbon::parse($matches[1]);
                if ($fileDate->lessThan(now()->subDays($days))) {
                    if ($softDelete) {
                        $trashPath = $path . "trash" . DIRECTORY_SEPARATOR . date('Ymd_His') . '_' . $file['name'];
                        $success = $fs->moveFile($file['full_path'], $trashPath);
                        $results[] = ['status' => $success, 'message' => "Soft delete per {$file['name']}"];
                    } else {
                        $success = $fs->deleteFile($file['full_path']);
                        $results[] = ['status' => $success, 'message' => "Hard delete per {$file['name']}"];
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Send email notification on backup failure.
     */
    protected function notifyFailure(string $connectionName, string $message): void
    {
        $recipient = config('backup-database.notification_email');
        if ($recipient) {
            try {
                Mail::to($recipient)->send(new BackupFailedMail($connectionName, $message));
            } catch (\Exception $e) {
                Log::error("Impossibile inviare mail di notifica: " . $e->getMessage());
            }
        }
    }

    /**
     * Normalize a path to use consistent directory separators.
     */
    protected function normalizePath(string $path): string
    {
        $path = str_replace(['/','\\'], DIRECTORY_SEPARATOR, $path);
        return rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * Ensure a directory exists on the local filesystem.
     * Does not create directories on SMB paths (handled by the SMB driver).
     */
    protected function ensureDirectoryExists(string $path): void
    {
        if (PHP_OS_FAMILY === 'Windows' || (!str_starts_with($path, '//') && !str_starts_with($path, 'smb:'))) {
             if (!is_dir($path)) {
                 mkdir($path, 0777, true);
             }
        }
    }

    /**
     * Write an entry to the package's daily log file.
     */
    protected function logOperation(string $type, string $message): void
    {
        if (!config('backup-database.enable_logging', true)) {
            return;
        }

        $logPath = storage_path('logs/backup-database-' . date('Y-m-d') . '.log');
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$type}] {$message}" . PHP_EOL;

        try {
            File::append($logPath, $logEntry);
        } catch (\Exception $e) {
            Log::error("Impossibile scrivere nel log del pacchetto: " . $e->getMessage());
        }
    }
}
