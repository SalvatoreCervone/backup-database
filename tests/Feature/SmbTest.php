<?php

use SalvatoreCervone\BackupDatabase\Tests\TestCase;

uses(TestCase::class);

use Illuminate\Support\Facades\Process;
use SalvatoreCervone\BackupDatabase\Drivers\Filesystem\LinuxSmbDriver;

it('parses smbclient ls output correctly and filters extensions', function () {
    Process::fake([
        '*' => Process::result(
            output: "  backup_2026-10-08.bak               A      1048576  Thu Oct  8 12:00:00 2026\n".
                    "  other_file.txt                      N         1024  Thu Oct  8 12:01:00 2026\n".
                    "  backup_2026-10-09.bak                      2097152  Fri Oct  9 12:00:00 2026\n".
                    "  45678 blocks available\n",
            exitCode: 0
        ),
    ]);

    $driver = new LinuxSmbDriver;
    $files = $driver->listFiles('//10.119.179.4/bak/', '*.bak');

    expect($files)->toHaveCount(2);
    expect($files[0]['name'])->toBe('backup_2026-10-08.bak');
    expect($files[0]['size'])->toBe(1048576);
    expect($files[0]['destination'])->toBe('//10.119.179.4/bak/');
    expect($files[0]['full_path'])->toBe('//10.119.179.4/bak/backup_2026-10-08.bak');

    expect($files[1]['name'])->toBe('backup_2026-10-09.bak');
    expect($files[1]['size'])->toBe(2097152);
});

it('handles smbclient with subfolder correctly', function () {
    Process::fake([
        '*' => Process::result(
            output: "  database.bak               A      1048576  Thu Oct  8 12:00:00 2026\n",
            exitCode: 0
        ),
    ]);

    $driver = new LinuxSmbDriver;
    $files = $driver->listFiles('//10.119.179.4/bak/subfolder', '*.bak');

    expect($files)->toHaveCount(1);
    expect($files[0]['name'])->toBe('database.bak');
    expect($files[0]['full_path'])->toBe('//10.119.179.4/bak/subfolder/database.bak');
});
