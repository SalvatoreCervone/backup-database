<?php

use SalvatoreCervone\BackupDatabase\Support\EncodingHelper;
use SalvatoreCervone\BackupDatabase\Tests\TestCase;

uses(TestCase::class);

it('keeps valid UTF-8 strings unchanged', function () {
    $utf8 = 'Backup completato con successo: Test DB!';
    expect(EncodingHelper::toUtf8($utf8))->toBe($utf8);
});

it('converts CP850 strings with accents to valid UTF-8', function () {
    // 0x8a is è, 0x85 is à, 0x8d is ì in CP850
    $cp850 = "BACKUP DATABASE \x8a stato interrotto in modo anomalo: errore \x85 riga 1";
    $converted = EncodingHelper::toUtf8($cp850);

    expect(mb_check_encoding($converted, 'UTF-8'))->toBeTrue();
    expect(json_encode(['message' => $converted], JSON_THROW_ON_ERROR))->toBeString();
});

it('converts Windows-1252 strings to valid UTF-8', function () {
    // 0xe8 is è in Windows-1252
    $win1252 = "BACKUP DATABASE \xe8 completato";
    $converted = EncodingHelper::toUtf8($win1252);

    expect(mb_check_encoding($converted, 'UTF-8'))->toBeTrue();
    expect(json_encode(['message' => $converted], JSON_THROW_ON_ERROR))->toBeString();
});

it('sanitizes recursively in arrays', function () {
    $data = [
        'status' => true,
        'message' => "Operazione \x8a riuscita",
        'nested' => [
            'file' => "test_\xe8.bak",
        ],
    ];

    $clean = EncodingHelper::toUtf8($data);

    expect(mb_check_encoding($clean['message'], 'UTF-8'))->toBeTrue();
    expect(mb_check_encoding($clean['nested']['file'], 'UTF-8'))->toBeTrue();
    expect(json_encode($clean, JSON_THROW_ON_ERROR))->toBeString();
});
