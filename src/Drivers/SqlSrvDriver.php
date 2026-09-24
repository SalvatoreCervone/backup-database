<?php

namespace SalvatoreCervone\BackupDatabase\Drivers;

use SalvatoreCervone\BackupDatabase\Contracts\BackupDriver;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

use SalvatoreCervone\BackupDatabase\Exceptions\DriverException;

class SqlSrvDriver implements BackupDriver
{
    public function backup(array $config): array
    {
        $dbhost = $config['db_host'];
        $dbname = $config['db_name'];
        $username = $config['db_username'];
        $password = $config['db_password'];
        $destinationPath = $config['destinationpath'];
        $daily = $config['daily'];
        $datetimeFormat = $config['datetimeFormat'] ?? 'Y-m-d_H-i';
        $binPath = config('backup-database.bin_paths.sqlcmd', '/opt/mssql-tools18/bin/sqlcmd');
        $compression = $config['compression'] ?? config('backup-database.mssql_compression', false);

        $name = $dbname . ($daily ? "_" . Carbon::now()->format($datetimeFormat) : "") . ".bak";
        $fullDestination = $destinationPath . $name;
        
        // Convert to Windows-style path for SQL Server (it runs on the DB server, not the web server)
        $sqlPath = str_replace('/', '\\', $fullDestination);

        // Sanitize: escape single quotes in the path to prevent SQL injection
        $sqlPath = str_replace("'", "''", $sqlPath);

        // Use QUOTENAME-style brackets for the database name (already standard T-SQL)
        $withOptions = 'INIT';
        if ($compression) {
            $withOptions .= ', COMPRESSION';
        }

        $script = "BACKUP DATABASE [{$this->sanitizeDbName($dbname)}] TO DISK = N'{$sqlPath}' WITH {$withOptions}";

        Log::info("SqlSrvDriver: Esecuzione backup per {$dbname}");

        $result = Process::run([
            $binPath,
            '-S', $dbhost,
            '-U', $username,
            '-P', $password,
            '-C',
            '-Q', $script
        ]);

        if ($result->successful()) {
            return [
                'status' => true,
                'message' => $result->output(),
                'file' => $name
            ];
        }

        throw new DriverException($result->errorOutput() ?: "Errore durante il backup MSSQL.");
    }

    public function restore(array $config, string $backupFilePath): array
    {
        $dbhost = $config['db_host'];
        $dbname = $config['db_name'];
        $username = $config['db_username'];
        $password = $config['db_password'];
        $binPath = config('backup-database.bin_paths.sqlcmd', '/opt/mssql-tools18/bin/sqlcmd');

        $sqlPath = str_replace('/', '\\', $backupFilePath);

        // Sanitize: escape single quotes in the path
        $sqlPath = str_replace("'", "''", $sqlPath);
        $safeDbName = $this->sanitizeDbName($dbname);
        
        // Comando per ripristinare sovrascrivendo (WITH REPLACE) e chiudendo le connessioni (ALTER DATABASE ... SET SINGLE_USER)
        $script = "ALTER DATABASE [{$safeDbName}] SET SINGLE_USER WITH ROLLBACK IMMEDIATE; " .
                  "RESTORE DATABASE [{$safeDbName}] FROM DISK = N'{$sqlPath}' WITH REPLACE; " .
                  "ALTER DATABASE [{$safeDbName}] SET MULTI_USER;";

        Log::info("SqlSrvDriver: Avvio ripristino per {$dbname} da {$backupFilePath}");

        $result = Process::run([
            $binPath,
            '-S', $dbhost,
            '-U', $username,
            '-P', $password,
            '-C',
            '-Q', $script
        ]);

        if ($result->successful()) {
            return [
                'status' => true,
                'message' => "Ripristino completato con successo: " . $result->output()
            ];
        }

        throw new DriverException($result->errorOutput() ?: "Errore durante il ripristino MSSQL.");
    }

    /**
     * Verify the integrity of a backup file without restoring it.
     *
     * Uses RESTORE VERIFYONLY to check that the backup set is complete
     * and that all volumes are readable.
     *
     * @param array $config Database connection configuration
     * @param string $backupFilePath Full path to the backup file
     * @return array Result with 'status' and 'message' keys
     */
    public function verify(array $config, string $backupFilePath): array
    {
        $dbhost = $config['db_host'];
        $username = $config['db_username'];
        $password = $config['db_password'];
        $binPath = config('backup-database.bin_paths.sqlcmd', '/opt/mssql-tools18/bin/sqlcmd');

        $sqlPath = str_replace('/', '\\', $backupFilePath);
        $sqlPath = str_replace("'", "''", $sqlPath);

        $script = "RESTORE VERIFYONLY FROM DISK = N'{$sqlPath}'";

        Log::info("SqlSrvDriver: Verifica integrità backup da {$backupFilePath}");

        $result = Process::run([
            $binPath,
            '-S', $dbhost,
            '-U', $username,
            '-P', $password,
            '-C',
            '-Q', $script
        ]);

        if ($result->successful()) {
            return [
                'status' => true,
                'message' => "Verifica completata: il backup è valido."
            ];
        }

        return [
            'status' => false,
            'message' => "Verifica fallita: " . ($result->errorOutput() ?: "Backup corrotto o non leggibile.")
        ];
    }

    /**
     * Sanitize a database name by removing characters that could break T-SQL bracket notation.
     * Brackets [ ] are the standard way to quote identifiers in T-SQL.
     * A closing bracket ] inside the name must be escaped as ]].
     */
    protected function sanitizeDbName(string $dbName): string
    {
        // Remove null bytes
        $dbName = str_replace("\0", '', $dbName);

        // Escape closing brackets (T-SQL escaping: ] becomes ]])
        return str_replace(']', ']]', $dbName);
    }
}
