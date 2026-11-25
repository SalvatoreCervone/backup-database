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
    $files = $driver->listFiles('//192.0.2.10/bak/', '*.bak');

    expect($files)->toHaveCount(2);
    expect($files[0]['name'])->toBe('backup_2026-10-08.bak');
    expect($files[0]['size'])->toBe(1048576);
    expect($files[0]['destination'])->toBe('//192.0.2.10/bak/');
    expect($files[0]['full_path'])->toBe('//192.0.2.10/bak/backup_2026-10-08.bak');

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
    $files = $driver->listFiles('//192.0.2.10/bak/subfolder', '*.bak');

    expect($files)->toHaveCount(1);
    expect($files[0]['name'])->toBe('database.bak');
    expect($files[0]['full_path'])->toBe('//192.0.2.10/bak/subfolder/database.bak');
});

it('creates the proper trash directory on Linux SMB moveFile and runs rename separately', function () {
    Process::fake([
        '*' => Process::result(output: '', errorOutput: '', exitCode: 0),
    ]);

    $driver = new LinuxSmbDriver;
    $moved = $driver->moveFile(
        '//192.0.2.10/bak/share/backups/MyDB_2026-10-01.bak',
        '//192.0.2.10/bak/share/backups/trash/20261008_MyDB_2026-10-01.bak'
    );

    expect($moved)->toBeTrue();

    Process::assertRan(function ($process) {
        $cmd = is_array($process->command) ? implode(' ', $process->command) : $process->command;

        return str_contains($cmd, 'mkdir "share\\backups\\trash"') && ! str_contains($cmd, 'mkdir "."');
    });

    Process::assertRan(function ($process) {
        $cmd = is_array($process->command) ? implode(' ', $process->command) : $process->command;

        return str_contains($cmd, 'rename "share\\backups\\MyDB_2026-10-01.bak" "share\\backups\\trash\\20261008_MyDB_2026-10-01.bak"');
    });
});

it('returns false on SMB moveFile when rename fails even if mkdir already exists', function () {
    Process::fake([
        '*mkdir*' => Process::result(output: '', errorOutput: 'NT_STATUS_OBJECT_NAME_COLLISION', exitCode: 1),
        '*rename*' => Process::result(output: '', errorOutput: 'NT_STATUS_ACCESS_DENIED', exitCode: 1),
    ]);

    $driver = new LinuxSmbDriver;
    $moved = $driver->moveFile(
        '//192.0.2.10/bak/MyDB_2026-10-01.bak',
        '//192.0.2.10/bak/trash/20261008_MyDB_2026-10-01.bak'
    );

    expect($moved)->toBeFalse();
});

it('skips directories and matches filter case-insensitively on SMB ls', function () {
    Process::fake([
        '*' => Process::result(
            output: "  trash                              D        0  Thu Oct  8 12:00:00 2026\n".
                    "  mydb_2026-10-08.bak                A  1048576  Thu Oct  8 12:00:00 2026\n",
            exitCode: 0
        ),
    ]);

    $driver = new LinuxSmbDriver;
    $files = $driver->listFiles('//192.0.2.10/bak/', 'MyDB*');

    expect($files)->toHaveCount(1)
        ->and($files[0]['name'])->toBe('mydb_2026-10-08.bak');
});
