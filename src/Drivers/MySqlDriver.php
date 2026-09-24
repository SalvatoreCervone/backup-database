<?php

namespace SalvatoreCervone\BackupDatabase\Drivers;

use SalvatoreCervone\BackupDatabase\Contracts\BackupDriver;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

use SalvatoreCervone\BackupDatabase\Exceptions\DriverException;

class MySqlDriver implements BackupDriver
{
    public function backup(array $config): array
    {
        $dbhost = $config['db_host'];
        $dbport = $config['db_port'] ?? '3306';
        $dbname = $config['db_name'];
        $username = $config['db_username'];
        $password = $config['db_password'];
        $destinationPath = $config['destinationpath'];
        $daily = $config['daily'];
        $datetimeFormat = $config['datetimeFormat'] ?? 'Y-m-d_H-i';
        $binPath = config('backup-database.bin_paths.mysqldump', 'mysqldump');

        $name = $dbname . ($daily ? "_" . Carbon::now()->format($datetimeFormat) : "") . ".sql";
        $fullDestination = $destinationPath . $name;

        Log::info("MySqlDriver: Esecuzione backup per {$dbname}");

        $result = Process::run([
            $binPath,
            "--user={$username}",
            "--password={$password}",
            "--host={$dbhost}",
            "--port={$dbport}",
            "--result-file={$fullDestination}",
            $dbname
        ]);

        if ($result->successful()) {
            return [
                'status' => true,
                'message' => "Backup completato con successo.",
                'file' => $name
            ];
        }

        throw new DriverException($result->errorOutput() ?: "Errore durante il backup MySQL.");
    }

    public function restore(array $config, string $backupFilePath): array
    {
        $dbhost = $config['db_host'];
        $dbport = $config['db_port'] ?? '3306';
        $dbname = $config['db_name'];
        $username = $config['db_username'];
        $password = $config['db_password'];
        $binPath = config('backup-database.bin_paths.mysql', 'mysql');

        Log::info("MySqlDriver: Avvio ripristino per {$dbname} da {$backupFilePath}");

        // Use shell redirection to stream the file directly to mysql client.
        // This avoids loading the entire dump file into PHP memory (which causes
        // OOM crashes on large databases). The mysql client reads from stdin via
        // the shell's file redirection, keeping memory usage constant.
        $escapedFile = escapeshellarg($backupFilePath);
        $escapedUser = escapeshellarg($username);
        $escapedPass = escapeshellarg($password);
        $escapedHost = escapeshellarg($dbhost);
        $escapedPort = escapeshellarg($dbport);
        $escapedDb = escapeshellarg($dbname);
        $escapedBin = escapeshellarg($binPath);

        $command = "{$escapedBin} --user={$escapedUser} --password={$escapedPass} --host={$escapedHost} --port={$escapedPort} {$escapedDb} < {$escapedFile}";

        $result = Process::run($command);

        if ($result->successful()) {
            return [
                'status' => true,
                'message' => "Ripristino MySQL completato."
            ];
        }

        throw new DriverException($result->errorOutput() ?: "Errore durante il ripristino MySQL.");
    }
}
