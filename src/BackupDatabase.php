<?php

namespace SalvatoreCervone\BackupDatabase;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

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

    // private function deleteFile($file)
    // {
    //     if (file_exists($file)) {
    //         unlink($file);
    //         return true;
    //     }
    //     return false;
    // }

    public function deleteFile(string $fullPath)
{
    try {
        // 1. Normalizzazione e splitting del percorso
        $normalized = str_replace(['\\', '//'], '/', $fullPath);
        $clean = ltrim($normalized, '/');

        $serverIp  = Str::before($clean, '/');
        $afterIp   = Str::after($clean, '/');
        $shareName = Str::before($afterIp, '/');
        $filePath  = Str::after($afterIp, '/');

        // 2. Recupero credenziali dai config
        $user = config('backup-database.smb_user');
        $pass = config('backup-database.smb_password');

        // Prepariamo il percorso per Windows (Samba preferisce i backslash nel comando del)
        $winPath = str_replace('/', '\\', $filePath);

        // 3. Esecuzione del comando smbclient
        $result = Process::run([
            'smbclient',
            "//{$serverIp}/{$shareName}",
            '-U', "{$user}%{$pass}",
            '-c', "del \"{$winPath}\""
        ]);

        // 4. Gestione esito e logging
        if ($result->successful()) {
            Log::info("SMB: File eliminato correttamente: {$fullPath}");
            return true;
        }

        // Se fallisce, scriviamo l'errore nel log ma non blocchiamo l'esecuzione
        Log::warning("SMB: Fallimento eliminazione file.", [
            'path'   => $fullPath,
            'stdout' => $result->output(),
            'stderr' => $result->errorOutput(),
            'exit_code' => $result->exitCode()
        ]);

        return false;

    } catch (\Exception $e) {
        // Gestione eccezioni impreviste (es. smbclient non installato o errori di rete)
        Log::error("SMB: Eccezione durante l'operazione deleteFile.", [
            'message' => $e->getMessage(),
            'path'    => $fullPath
        ]);

        return false;
    }
}

private function checkPreviousBackups($destinationpath, $dbname, $days_for_delete, $soft_delete)
{
    if ($days_for_delete === null) {
        return [];
    }

    $result = [];

    // 1. Normalizzazione e splitting (come nella deleteFile)
    $normalized = str_replace(['\\', '//'], '/', $destinationpath);
    $clean = ltrim($normalized, '/');
    $serverIp = Str::before($clean, '/');
    $afterIp = Str::after($clean, '/');
    $shareName = Str::before($afterIp, '/');
    $folderPath = Str::after($afterIp, '/'); // La sottocartella dove cercare

    // 2. Eseguiamo 'ls' tramite smbclient per vedere i file remoti
    $user = config('backup-database.smb_user');
    $pass = config('backup-database.smb_password');

    // Il comando 'ls' accetta wildcard
    $searchMask = $folderPath . '/' . $dbname . "*.bak";
    $searchMask = str_replace('/', '\\', $searchMask);

    $process = Process::run([
        'smbclient',
        "//{$serverIp}/{$shareName}",
        '-U', "{$user}%{$pass}",
        '-c', "ls \"{$searchMask}\""
    ]);

    if ($process->failed()) {
        Log::error("SMB ls failed: " . $process->errorOutput());
        return [];
    }

    // 3. Analizziamo l'output di smbclient
    // L'output tipico è: "  nomefile.bak                  A      1234  Fri Feb 21 04:00:00 2026"
    $lines = explode("\n", $process->output());

    foreach ($lines as $line) {
        $line = trim($line);
        // Saltiamo le righe vuote o quelle che indicano lo spazio libero
        if (empty($line) || str_contains($line, 'blocks available')) continue;

        // Estraiamo il nome del file (di solito è la prima parte della riga)
        // Usiamo una regex semplice per prendere il nome del file prima degli attributi
        if (preg_match('/^\s*(.*?)\s+[ADHR]/', $line, $matches)) {
            $fileName = trim($matches[1]);

            // Ricostruiamo il percorso completo per la cancellazione
            $fullRemotePath = "//{$serverIp}/{$shareName}/" . ($folderPath ? $folderPath . "/" : "") . $fileName;

            // Chiamiamo la logica di cancellazione basata sulla data
            // Nota: deleteAfter deve essere in grado di gestire il file remoto o devi passarle la data estratta dal ls

                $result[] =  $this->deleteAfter($days_for_delete, $fullRemotePath, $soft_delete);

        }
    }

    return array_filter($result);
}

    private function checkPreviousBackups_old($destinationpath, $dbname, $days_for_delete, $soft_delete)
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

private function deleteAfter($days, $fullPath, $soft_delete)
{
    if (preg_match('/(\d{4}-\d{2}-\d{2})/', $fullPath, $matches)) {
        $fileDate = \Carbon\Carbon::parse($matches[1]);
        $expirationDate = now()->subDays($days);

        if ($fileDate->lessThan($expirationDate)) {

            $normalized = str_replace(['\\', '//'], '/', $fullPath);
            $clean = ltrim($normalized, '/');

            $serverIp  = Str::before($clean, '/');
            $rest      = Str::after($clean, '/');
            $shareName = Str::before($rest, '/');
            $filePath  = Str::after($rest, '/');

            $user = config('backup-database.smb_user');
            $pass = config('backup-database.smb_password');

            if ($soft_delete) {
                $fileName = basename($filePath);
                // Aggiungiamo un timestamp per evitare collisioni di nomi nel trash
                $trashName = date('Ymd_His') . '_' . $fileName;
                $trashPath = "trash/" . $trashName;

                /**
                 * Usiamo il prefisso "-" davanti a mkdir.
                 * In molti client questo ignora l'errore se la cartella esiste.
                 * Se smbclient non lo supporta, concateniamo i comandi con ";"
                 * così rename viene eseguito anche se mkdir fallisce.
                 */
                $smbCommand = "mkdir trash; rename \"{$filePath}\" \"{$trashPath}\"";
                $actionLog = "Soft Delete (Spostato in trash)";
            } else {
                $smbCommand = "del \"{$filePath}\"";
                $actionLog = "Hard Delete (Eliminato)";
            }

            // Esecuzione
            $result = \Illuminate\Support\Facades\Process::run([
                'smbclient',
                "//{$serverIp}/{$shareName}",
                '-U', "{$user}%{$pass}",
                '-c', str_replace('/', '\\', $smbCommand)
            ]);

            /**
             * IMPORTANTE: Se abbiamo fatto un soft_delete, l'exit code potrebbe essere 1
             * perché 'mkdir trash' fallisce se la cartella esiste.
             * Dobbiamo controllare se il file originale è sparito o se il rename ha avuto successo.
             */
            if ($result->successful() || ($soft_delete && str_contains($result->errorOutput(), 'NT_STATUS_OBJECT_NAME_COLLISION'))) {
                \Illuminate\Support\Facades\Log::info("{$actionLog}: {$fullPath}");
                return true;
            }

            \Illuminate\Support\Facades\Log::warning("Fallimento SMB su {$fullPath}: " . $result->errorOutput());
            return false;
        }
    }
    return null;
}

    // function deleteAfter($days_for_delete, $filename, $soft_delete)
    // {
    //     if (!file_exists($filename)) {
    //         return ['status' => false, 'message' => "File {$filename} not found."];
    //     }
    //     $date_file = Carbon::parse(filemtime($filename));
    //     $date_now_sub_for_delate = Carbon::now()->subDays($days_for_delete);

    //     if ($date_now_sub_for_delate > $date_file) {
    //         if ($soft_delete) {
    //             $fileinfo = pathinfo($filename);

    //             $trash = $fileinfo['dirname']  . '\\trash\\';
    //             $resultCreateFolder = $this->createFolder($trash);

    //             if ($resultCreateFolder['status'] == false) {
    //                 return $resultCreateFolder;
    //             }
    //             $filenameTrash = $trash . basename($filename);
    //             rename($filename, $filenameTrash);
    //         } else {
    //             unlink($filename);
    //         }

    //         return ['status' => true, 'message' => "File {$filename} deleted."];
    //     }
    // }


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
