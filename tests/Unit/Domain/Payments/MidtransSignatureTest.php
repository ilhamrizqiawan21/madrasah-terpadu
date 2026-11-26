<?php

declare(strict_types=1);

use App\Domain\Payments\MidtransSignature;

const TEST_SERVER_KEY = 'SB-Mid-server-TEST';

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function signedPayload(array $overrides = []): array
{
    $payload = [
        'order_id' => 'INV-TEST-1',
        'status_code' => '200',
        'gross_amount' => '150000.00',
        'transaction_status' => 'settlement',
    ];

    $payload['signature_key'] = hash(
        'sha512',
        $payload['order_id'].$payload['status_code'].$payload['gross_amount'].TEST_SERVER_KEY,
    );

    return array_merge($payload, $overrides);
}

it('menerima notifikasi yang ditandatangani dengan server key yang benar', function (): void {
    expect((new MidtransSignature(TEST_SERVER_KEY))->isValid(signedPayload()))->toBeTrue();
});

it('menerima signature dalam huruf besar', function (): void {
    $payload = signedPayload();
    $payload['signature_key'] = strtoupper($payload['signature_key']);

    expect((new MidtransSignature(TEST_SERVER_KEY))->isValid($payload))->toBeTrue();
});

it('menolak notifikasi yang nominalnya diubah setelah ditandatangani', function (): void {
    $payload = signedPayload(['gross_amount' => '1500.00']);

    expect((new MidtransSignature(TEST_SERVER_KEY))->isValid($payload))->toBeFalse();
});

it('menolak notifikasi bila nominal diformat ulang', function (): void {
    // "150000.00" and "150000" are different strings to the hash.
    $payload = signedPayload(['gross_amount' => '150000']);

    expect((new MidtransSignature(TEST_SERVER_KEY))->isValid($payload))->toBeFalse();
});

it('menolak notifikasi yang ditandatangani dengan server key lain', function (): void {
    expect((new MidtransSignature('SB-Mid-server-OTHER'))->isValid(signedPayload()))->toBeFalse();
});

it('menolak notifikasi yang kehilangan field wajib', function (string $field): void {
    $payload = signedPayload();
    unset($payload[$field]);

    expect((new MidtransSignature(TEST_SERVER_KEY))->isValid($payload))->toBeFalse();
})->with(['order_id', 'status_code', 'gross_amount', 'signature_key']);

it('menolak field yang bukan string', function (): void {
    $payload = signedPayload(['gross_amount' => 150000]);

    expect((new MidtransSignature(TEST_SERVER_KEY))->isValid($payload))->toBeFalse();
});

it('menolak dibuat tanpa server key', function (): void {
    new MidtransSignature('');
})->throws(InvalidArgumentException::class);
