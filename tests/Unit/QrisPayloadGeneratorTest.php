<?php

use App\Exceptions\InvalidQrisPayload;
use App\Services\Qris\QrisPayloadGenerator;

const STATIC_QRIS = '00020101021126370014ID.CO.QRIS.WWW0115ID12345678901235204729953033605802ID5907LAUNDRY6007JAKARTA630489DC';

it('calculates the CRC16 CCITT false checksum used by QRIS', function (): void {
    expect(app(QrisPayloadGenerator::class)->crc16('123456789'))->toBe('29B1');
});

it('converts a valid static QRIS payload into a deterministic dynamic amount payload', function (): void {
    $payload = app(QrisPayloadGenerator::class)->generate(STATIC_QRIS, 12500);

    expect($payload)->toBe(
        '00020101021226370014ID.CO.QRIS.WWW0115ID12345678901235204729953033605405125005802ID5907LAUNDRY6007JAKARTA63043856',
    );
});

it('replaces an existing amount instead of duplicating it', function (): void {
    $generator = app(QrisPayloadGenerator::class);
    $firstPayload = $generator->generate(STATIC_QRIS, 12500);

    expect($generator->generate($firstPayload, 25000))->toContain('540525000')
        ->not->toContain('540512500');
});

it('rejects a payload with an invalid checksum', function (): void {
    app(QrisPayloadGenerator::class)->generate(substr(STATIC_QRIS, 0, -4).'FFFF', 12500);
})->throws(InvalidQrisPayload::class, 'Checksum QRIS tidak cocok.');

it('rejects malformed and duplicate fields', function (string $payload): void {
    app(QrisPayloadGenerator::class)->generate($payload, 12500);
})->with([
    'truncated field' => substr(STATIC_QRIS, 0, -7),
    'duplicate field' => '000201000201'.substr(STATIC_QRIS, 6),
])->throws(InvalidQrisPayload::class);

it('rejects a non-positive amount', function (): void {
    app(QrisPayloadGenerator::class)->generate(STATIC_QRIS, 0);
})->throws(InvalidQrisPayload::class, 'Nominal QRIS harus lebih dari nol.');
