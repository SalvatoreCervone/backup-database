<?php

namespace SalvatoreCervone\BackupDatabase\Security;

use SalvatoreCervone\BackupDatabase\Exceptions\BackupException;

class PathValidator
{
    /**
     * Validate that a given file path is strictly contained within one of the
     * allowed destination paths configured for backup connections.
     *
     * This prevents path traversal attacks (e.g., ../../etc/passwd) by ensuring
     * the resolved path starts with an allowed base directory.
     *
     * @param string $filePath The file path to validate (from user input)
     * @param array|null $allowedBasePaths Optional explicit list of allowed base paths. 
     *                                      If null, reads from config.
     * @return string The validated, normalized file path
     * @throws BackupException If the path is outside all allowed directories
     */
    public static function validate(string $filePath, ?array $allowedBasePaths = null): string
    {
        if (empty($filePath)) {
            throw new BackupException('Il percorso del file non può essere vuoto.');
        }

        // Gather allowed base paths from config if not provided
        if ($allowedBasePaths === null) {
            $allowedBasePaths = self::getAllowedPaths();
        }

        if (empty($allowedBasePaths)) {
            throw new BackupException('Nessun percorso di destinazione configurato.');
        }

        // Normalize the input path
        $normalizedInput = self::normalizePath($filePath);

        // Check if the path is an SMB/UNC path (starts with // or \\)
        if (self::isSmbPath($normalizedInput)) {
            return self::validateSmbPath($normalizedInput, $allowedBasePaths);
        }

        return self::validateLocalPath($normalizedInput, $allowedBasePaths);
    }

    /**
     * Validate that only a basename (filename without directory separators) was provided,
     * and resolve it against a specific connection's destination path.
     *
     * @param string $fileName The file name (should be a basename)
     * @param string $connectionName The connection name to look up the destination path
     * @return string The full resolved file path
     * @throws BackupException If the filename contains directory traversal or connection not found
     */
    public static function validateFileName(string $fileName, string $connectionName): string
    {
        if (empty($fileName)) {
            throw new BackupException('Il nome del file non può essere vuoto.');
        }

        // Reject any path separators or traversal sequences in the filename
        if (preg_match('/[\/\\\\]/', $fileName) || str_contains($fileName, '..')) {
            throw new BackupException('Il nome del file contiene caratteri non validi.');
        }

        // Look up the connection's destination path
        $connections = config('backup-database.listconnections', []);
        $connection = collect($connections)->firstWhere('connection', $connectionName);

        if (!$connection) {
            throw new BackupException("Configurazione connessione '{$connectionName}' non trovata.");
        }

        $basePath = self::normalizePath($connection['destinationpath']);

        return $basePath . $fileName;
    }

    /**
     * Gather all configured destination paths for backup connections.
     */
    protected static function getAllowedPaths(): array
    {
        $connections = config('backup-database.listconnections', []);
        $paths = [];

        foreach ($connections as $connection) {
            if (!empty($connection['destinationpath'])) {
                $paths[] = self::normalizePath($connection['destinationpath']);
            }
        }

        return array_unique($paths);
    }

    /**
     * Validate a local filesystem path against allowed base directories.
     */
    protected static function validateLocalPath(string $path, array $allowedBasePaths): string
    {
        // Use realpath for the directory portion if it exists
        $directory = dirname($path);
        $resolvedDir = realpath($directory);

        // If the directory can be resolved, use the real path
        if ($resolvedDir !== false) {
            $resolvedPath = $resolvedDir . DIRECTORY_SEPARATOR . basename($path);
        } else {
            // Directory doesn't exist yet (could be valid for new backups)
            // Still check the normalized path for traversal patterns
            $resolvedPath = $path;
        }

        // Normalize for comparison
        $normalizedResolved = self::normalizePath(dirname($resolvedPath)) ;

        foreach ($allowedBasePaths as $basePath) {
            $normalizedBase = self::normalizePath($basePath);

            if (str_starts_with($normalizedResolved, $normalizedBase)) {
                return $resolvedPath;
            }
        }

        throw new BackupException(
            'Accesso negato: il percorso del file è al di fuori delle directory di backup consentite.'
        );
    }

    /**
     * Validate an SMB/UNC path against allowed base directories.
     * Since realpath() doesn't work on SMB paths, we do string-based validation.
     */
    protected static function validateSmbPath(string $path, array $allowedBasePaths): string
    {
        // Check for obvious traversal patterns
        if (str_contains($path, '..')) {
            throw new BackupException(
                'Accesso negato: il percorso contiene sequenze di attraversamento directory (..).'
            );
        }

        $normalizedPath = self::normalizePath(dirname($path) . '/');

        foreach ($allowedBasePaths as $basePath) {
            if (!self::isSmbPath($basePath)) {
                continue;
            }

            $normalizedBase = self::normalizePath($basePath);

            if (str_starts_with($normalizedPath, $normalizedBase)) {
                return $path;
            }
        }

        throw new BackupException(
            'Accesso negato: il percorso SMB è al di fuori delle directory di backup consentite.'
        );
    }

    /**
     * Normalize a path to use consistent directory separators.
     */
    protected static function normalizePath(string $path): string
    {
        $path = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);
        return rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * Check if a path looks like an SMB/UNC network path.
     */
    protected static function isSmbPath(string $path): bool
    {
        return str_starts_with($path, '//') 
            || str_starts_with($path, '\\\\') 
            || str_starts_with($path, 'smb:');
    }
}
