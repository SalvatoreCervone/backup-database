<?php

use Illuminate\Support\Facades\File;
use SalvatoreCervone\BackupDatabase\Drivers\Filesystem\NativeDriver;
use SalvatoreCervone\BackupDatabase\Tests\TestCase;

uses(TestCase::class);

it('lists files using scandir with filter and case insensitivity', function () {
    $tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'backup_test_'.uniqid();
    mkdir($tempDir, 0777, true);

    file_put_contents($tempDir.DIRECTORY_SEPARATOR.'test1.bak', 'dummy data');
    file_put_contents($tempDir.DIRECTORY_SEPARATOR.'test2.BAK', 'dummy data 2');
    file_put_contents($tempDir.DIRECTORY_SEPARATOR.'test3.sql', 'dummy data 3');
    file_put_contents($tempDir.DIRECTORY_SEPARATOR.'ignore.txt', 'dummy data 4');

    $driver = new NativeDriver;
    $bakFiles = $driver->listFiles($tempDir, '*.bak');

    expect($bakFiles)->toHaveCount(2);
    $names = array_column($bakFiles, 'name');
    expect($names)->toContain('test1.bak', 'test2.BAK');

    $sqlFiles = $driver->listFiles($tempDir, '*.sql');
    expect($sqlFiles)->toHaveCount(1);
    expect($sqlFiles[0]['name'])->toBe('test3.sql');

    File::deleteDirectory($tempDir);
});
