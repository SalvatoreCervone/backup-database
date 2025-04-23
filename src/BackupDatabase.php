<?php

namespace SalvatoreCervone\BackupDatabase;

use Carbon\Carbon;
use Illuminate\Support\Str;

class BackupDatabase
{
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
            $days_for_delete = $connection['days_for_delete'] ?? null;
            $soft_delete = $connection['soft_delete'] ?? false;
            $daily = $connection['daily'];
            $destinationpath = $connection['destinationpath'];

            $resultPrevius = $this->checkPreviousBackups($destinationpath, $dbname, $days_for_delete, $soft_delete);
            $resultCreateFolder = $this->createFolder($destinationpath);
            if ($resultCreateFolder['status'] == false) {
                $result[] = $resultCreateFolder;
                continue;
            }
            if ($driver == 'sqlsrv') {
                $name = $dbname  .  ($daily ? "_" . Carbon::now()->format($connection['datetimeFormat']) : "") . ".bak";
                $script = "BACKUP DATABASE " . $dbname . " TO DISK= '" . $destinationpath . $name . "'";
                $resultShell = shell_exec('sqlcmd -S ' . $dbhost . ' -U ' .  $username . ' -P ' . $password . ' -Q "' . $script . '"');
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
            } else {

                $result[] = ['status' => false, 'message' => "Unsupported database driver: {$driver}"];;
            }
        }

        return array_merge($result,  $resultPrevius);
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

        $listGlobalFile = null;
        foreach ($urldascandire as $connection) {


            $connectionDatabase = $connection['connection'];

            $listBackups[$connectionDatabase] = null;

            $driver = config("database.connections.{$connectionDatabase}.driver");
            if ($driver == 'sqlsrv') {
                $destinationpath = $connection['destinationpath'];
                foreach (glob($destinationpath . "*.bak") as $file) {
                    $listBackups[$connectionDatabase][] = [
                        'name' => basename($file),
                        'size' => filesize($file),
                        'modified' => date("F d Y H:i:s.", filemtime($file)),
                        'destination' => $destinationpath,
                    ];
                }
            }
            $listGlobalFile[$connectionDatabase] = array_merge($listGlobalFile[$connectionDatabase] ?? [], $listBackups[$connectionDatabase] ?? []);
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
        $supportedDrivers = ['sqlsrv', 'mysql'];
        if (!in_array($driver, $supportedDrivers)) {
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
