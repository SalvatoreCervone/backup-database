<?php

namespace SalvatoreCervone\BackupDatabase;

use Carbon\Carbon;
use PSpell\Config;

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
            if ($driver == 'sqlsrv') {
                $dbhost = $connection['db_host'] ?? config("database.connections.{$connectionDatabase}.host");
                $dbname = $connection['db_name'] ?? config("database.connections.{$connectionDatabase}.database");
                $username = $connection['db_username'] ?? config("database.connections.{$connectionDatabase}.username");
                $password = $connection['db_password'] ?? config("database.connections.{$connectionDatabase}.password");
                $day_for_delete = $connection['day_for_delete'] ?? null;
                $soft_delete = $connection['soft_delete'] ?? false;
                $daily = $connection['daily'];
                $destinationpath = $connection['destinationpath'];
                $name = $dbname  .  ($daily ? "_" . Carbon::now()->format($connection['datetimeFormat']) : "") . ".bak";

                $resultPrevius = $this->checkPreviousBackups($destinationpath, $dbname, $day_for_delete, $soft_delete);
                $resultCreateFolder = $this->createFolder($destinationpath);
                if ($resultCreateFolder['status'] == false) {
                    $result[] = $resultCreateFolder['error'];
                    continue;
                }
                $script = "BACKUP DATABASE " . $dbname . " TO DISK= '" . $destinationpath . $name . "'";
                $resultShell = shell_exec('sqlcmd -S ' . $dbhost . ' -U ' .  $username . ' -P ' . $password . ' -Q "' . $script . '"');
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
        foreach ($listconnections as $connection) {
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
        }
        return $listBackups;
    }
    public function delete()
    {
        $file = request()->input('file');

        return $this->deleteFile($file);
    }

    private function deleteFile($file)
    {
        if (file_exists($file)) {
            unlink($file);
            return true;
        }
        return false;
    }

    private function checkPreviousBackups($destinationpath, $dbname, $day_for_delete, $soft_delete)
    {
        if ($day_for_delete === null) {
            return [];
        }
        $result = null;
        foreach (glob($destinationpath . $dbname . "*.bak") as $file) {
            $result[] = $this->deleteAfter($day_for_delete, $file, $soft_delete);
        }

        return array_filter(is_array($result) ? $result : []);
    }

    function deleteAfter($day_for_delete, $filename, $soft_delete)
    {
        if (!file_exists($filename)) {
            return ['status' => false, 'message' => "File {$filename} not found."];
        }
        $date_file = Carbon::parse(filemtime($filename));
        $date_now_sub_for_delate = Carbon::now()->subDays($day_for_delete);

        if ($date_now_sub_for_delate > $date_file) {
            if ($soft_delete) {
                $fileinfo = pathinfo($filename);

                $trash = $fileinfo['dirname']  . '\\trash\\';
                $resultCreateFolder = $this->createFolder($trash);

                if ($resultCreateFolder['status'] == false) {
                    return ['status' => false, 'message' => "Folder {$trash}  not writable."];
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
            return ['status' => false, 'error' => "Destination path is not writable: {$destinationpath}"];
        }
        return ['status' => true];
    }
}
