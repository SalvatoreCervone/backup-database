<?php

namespace SalvatoreCervone\BackupDatabase;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Icewind\SMB\ServerFactory;
use Icewind\SMB\BasicAuth;
use Icewind\SMB\System;
use Icewind\SMB\Wrapped\Server as WrappedServer;
use Icewind\SMB\Options;
use Icewind\SMB\TimeZoneProvider;

class BackupDatabase
{

    public  $supportedDrivers = ['sqlsrv', 'mysql'];

    public function __construct()
    {
        // Constructor code here
    }

    public function backup()
    {
        $result = null;
        $listconnections = config('backup-database.listconnections');
        foreach ($listconnections as $connection) {
            $connectionDatabase = $connection['connection'];
            $driver = config("database.connections.{$connectionDatabase}.driver");
            Log::info("BackupDatabase: Starting backup for connection: {$connectionDatabase} with driver: {$driver}");
            $resultCheckDriver = $this->checkDriver($driver);
            if (!$resultCheckDriver['status']) {
                $result[] = $resultCheckDriver;
                continue;
            }
            $dbhost = $connection['db_host'] ?? config("database.connections.{$connectionDatabase}.host");
            $dbport = $connection['db_port'] ?? config("database.connections.{$connectionDatabase}.port");
            $dbname = $connection['db_name'] ?? config("database.connections.{$connectionDatabase}.database");
            $username = $connection['db_username'] ?? config("database.connections.{$connectionDatabase}.username");
            $password = $connection['db_password'] ?? config("database.connections.{$connectionDatabase}.password");
            Log::info("BackupDatabase: Using connection details - Host: {$dbhost}, Port: {$dbport}, Database: {$dbname}, Username: {$username}");
            $daily = $connection['daily'];
            $destinationpath = $connection['destinationpath'];
            $resultPrevius = [];
            //$resultPrevius = $this->checkPreviousBackups($destinationpath, $dbname, $days_for_delete, $soft_delete);
            $resultCreateFolder = $this->createFolder($destinationpath);
            if ($resultCreateFolder['status'] == false) {
                Log::info("Error create folder: {$resultCreateFolder['message']}");
                Log::info("{$destinationpath}");
                $result[] = $resultCreateFolder;
                continue;
            }

            $days_for_delete = $connection['days_for_delete'] ?? null;
            $soft_delete = $connection['soft_delete'] ?? false;
            if ($driver == 'sqlsrv') {
                $destinationpath = str_replace('\\', '\\\\', $destinationpath);
                $name = $dbname  .  ($daily ? "_" . Carbon::now()->format($connection['datetimeFormat']) : "") . ".bak";
                $script = "BACKUP DATABASE " . $dbname . " TO DISK= '" . $destinationpath . $name . "' WITH INIT";
                Log::info("BackupDatabase: Executed SQL command: {$script}");
                $script_completo='sqlcmd -S ' . $dbhost . ' -U ' .  $username . ' -P ' . $password . ' -C -Q "' . $script . '"';
                Log::info("BackupDatabase: Complete Command:  {$script_completo}");
                $resultShell = shell_exec('/opt/mssql-tools18/bin/sqlcmd -S ' . $dbhost . ' -U ' .  $username . ' -P ' . $password . ' -C -Q "' . $script . '"');
                Log::info("BackupDatabase: Shell command result: {$resultShell}");
                if (Str::startsWith($resultShell, 'Messaggio')) {
                    $result[] = ['status' => false, 'message' => "Error: {$resultShell}"];
                    continue;
                }
                $resultPrevius = $this->checkPreviousBackups($destinationpath, $dbname, $days_for_delete, $soft_delete);
                $result[] = ['status' => true, 'message' => $resultShell];
            } elseif ($driver == 'mysql') {
                $name = $dbname  .  ($daily ? "_" . Carbon::now()->format($connection['datetimeFormat']) : "") . ".sql";
                $script = "mysqldump --user={$username} --password={$password} --host={$dbhost} --port={$dbport} {$dbname} > {$destinationpath}{$name}";
                $resultShell = shell_exec($script);
                $result[] = ['status' => true, 'message' => $resultShell];
            }
        }

        return response()->json(array_merge($result,  $resultPrevius), 200);
    }

    public function restore()
    {
        // Restore logic here
    }

    public function getStatus()
    {
        $listBackups = null;
        $listconnections = config('backup-database.listconnections');
        $urldascandire = collect($listconnections)->map(function ($item) {
            return collect($item)->only(['destinationpath', 'connection'])->toArray();
        })->unique();

        foreach ($urldascandire as $connection) {
            $connectionDatabase = $connection['connection'];
            $destinationpath = $connection['destinationpath'];

            // 1. Parsing del percorso
            $path = str_replace(['smb:', '\\\\','\\'], ['', '/','/'], $destinationpath);

            $path = ltrim($path, '/');
            $parts = explode('/', $path);

            $host = array_shift($parts);
            $shareName = array_shift($parts);
            $remainingPath = implode('/', $parts) ?: '';

            // 2. Credenziali
            $user = config('backup-database.smb_user');
            $pass = config('backup-database.smb_password');

            // 3. Esecuzione comando (quello che abbiamo testato con successo)
            // Usiamo l'opzione -D per entrare nella sottocartella se esiste
            $cdCommand = $remainingPath ? "cd \"$remainingPath\"; " : "";
            $cmd = "smbclient //{$host}/{$shareName} -U \"{$user}%{$pass}\" -c '{$cdCommand}ls' 2>&1";

            $output = shell_exec($cmd);

            $listBackups = [];
            if ($output) {
                // 4. Parsing dell'output di smbclient
                // Una riga tipica è: "  nomefile.bak           A    12345  Thu Feb 19 12:00:00 2026"
                $lines = explode("\n", $output);

                foreach ($lines as $line) {
                    $line = trim($line);

                    // Filtriamo solo i file .bak (case insensitive)
                    if (preg_match('/^(.*?\.bak)\s+[A-Z]*\s+(\d+)\s+(.*)$/i', $line, $matches)) {
                        $fileName = trim($matches[1]);
                        $size = $matches[2];
                        $dateStr = $matches[3];

                        $listBackups[] = [
                            'name'        => $fileName,
                            'size'        => (int)$size,
                            'modified'    => $dateStr, // Già formattata da Samba
                            'destination' => $destinationpath,
                        ];
                    }
                }
            }

            $listGlobalFile[$connectionDatabase] = array_merge(
                $listGlobalFile[$connectionDatabase] ?? [],
                $listBackups
            );
        }

        return $listGlobalFile;
    }
    public function delete()
    {
        $file = request()->input('file');

        return $this->deleteFile($file);
    }

    private function checkDriver($driver)
    {

        if (!in_array($driver, $this->supportedDrivers)) {
            return ['status' => false, 'message' => "Unsupported database driver: {$driver}"];
        }
        return ['status' => true];
    }

    private function deleteFile($file)
    {
        if (file_exists($file)) {
            unlink($file);
            return true;
        }
        return false;
    }

    private function checkPreviousBackups($destinationpath, $dbname, $days_for_delete, $soft_delete)
    {
        if ($days_for_delete === null) {
            return [];
        }
        $result = null;
        foreach (glob($destinationpath . $dbname . "*.bak") as $file) {
            $result[] = $this->deleteAfter($days_for_delete, $file, $soft_delete);
        }

        return array_filter(is_array($result) ? $result : []);
    }

    function deleteAfter($days_for_delete, $filename, $soft_delete)
    {
        if (!file_exists($filename)) {
            return ['status' => false, 'message' => "File {$filename} not found."];
        }
        $date_file = Carbon::parse(filemtime($filename));
        $date_now_sub_for_delate = Carbon::now()->subDays($days_for_delete);

        if ($date_now_sub_for_delate > $date_file) {
            if ($soft_delete) {
                $fileinfo = pathinfo($filename);

                $trash = $fileinfo['dirname']  . '\\trash\\';
                $resultCreateFolder = $this->createFolder($trash);

                if ($resultCreateFolder['status'] == false) {
                    return $resultCreateFolder;
                }
                $filenameTrash = $trash . basename($filename);
                rename($filename, $filenameTrash);
            } else {
                unlink($filename);
            }

            return ['status' => true, 'message' => "File {$filename} deleted."];
        }
    }


    private function createFolder($destinationpath)
    {
        if (!is_dir($destinationpath)) {
                mkdir($destinationpath, 0777, true);
        }
        if (!is_writable($destinationpath)) {
            return ['status' => false, 'message' => "Destination path is not writable: {$destinationpath}"];
        }
        return ['status' => true];
    }
}
