<?php

declare(strict_types=1);

use App\Domain\Payments\MidtransSignature;
use App\Exceptions\Payments\MidtransRequestException;
use App\Exceptions\Payments\MidtransTransactionNotFoundException;
use App\Exceptions\Payments\MidtransUnavailableException;
use App\Services\Payments\MidtransClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'services.midtrans.server_key' => 'SB-Mid-server-KLIEN-UJI',
        'services.midtrans.is_production' => false,
        'app.url' => 'https://madrasah.test',
    ]);
});

function midtransTestClient(bool $production = false): MidtransClient
{
    return new MidtransClient('SB-Mid-server-KLIEN-UJI', $production, 'https://madrasah.test/webhooks/midtrans', 0);
}

it('membuat transaksi Snap sandbox dengan Basic Auth dan header override notifikasi', function (): void {
    Http::fake([
        'app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
            'token' => 'snap-token-1',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/snap-token-1',
        ], 201),
    ]);

    $result = midtransTestClient()->createSnapTransaction([
        'transaction_details' => ['order_id' => 'INV-01TEST', 'gross_amount' => 150000],
    ]);

    expect($result)->toBe([
        'token' => 'snap-token-1',
        'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/snap-token-1',
    ]);

    Http::assertSent(function (Request $request): bool {
        return $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('SB-Mid-server-KLIEN-UJI:'))
            && $request->hasHeader('X-Override-Notification', 'https://madrasah.test/webhooks/midtrans')
            && $request['transaction_details']['gross_amount'] === 150000;
    });
});

it('memakai URL produksi saat is_production menyala', function (): void {
    Http::fake([
        'app.midtrans.com/snap/v1/transactions' => Http::response(['token' => 't', 'redirect_url' => 'https://app.midtrans.com/r'], 201),
        'api.midtrans.com/v2/*' => Http::response(['status_code' => '200', 'transaction_status' => 'settlement']),
    ]);

    $client = midtransTestClient(production: true);
    $client->createSnapTransaction(['transaction_details' => ['order_id' => 'INV-1', 'gross_amount' => 1]]);
    $client->getStatus('INV-1');

    Http::assertSent(fn (Request $r): bool => $r->url() === 'https://app.midtrans.com/snap/v1/transactions');
    Http::assertSent(fn (Request $r): bool => $r->url() === 'https://api.midtrans.com/v2/INV-1/status');
    expect($client->snapJsUrl())->toBe('https://app.midtrans.com/snap/snap.js');
});

it('mengambil status transaksi dari Core API sandbox', function (): void {
    Http::fake([
        'api.sandbox.midtrans.com/v2/INV-01TEST/status' => Http::response([
            'status_code' => '200',
            'order_id' => 'INV-01TEST',
            'transaction_status' => 'settlement',
            'gross_amount' => '150000.00',
        ]),
    ]);

    $status = midtransTestClient()->getStatus('INV-01TEST');

    expect($status['transaction_status'])->toBe('settlement')
        ->and($status['gross_amount'])->toBe('150000.00');
});

it('melempar galat bertipe untuk 4xx tanpa mencoba ulang', function (): void {
    Http::fake([
        'app.sandbox.midtrans.com/*' => Http::response(['error_messages' => ['transaction_details.order_id sudah digunakan']], 400),
    ]);

    try {
        midtransTestClient()->createSnapTransaction(['transaction_details' => ['order_id' => 'INV-1', 'gross_amount' => 1]]);
        $this->fail('Seharusnya melempar MidtransRequestException.');
    } catch (MidtransRequestException $e) {
        expect($e->statusCode)->toBe(400)
            ->and($e->errorMessages)->toBe(['transaction_details.order_id sudah digunakan'])
            ->and($e->getMessage())->not->toContain('SB-Mid-server-KLIEN-UJI');
    }

    Http::assertSentCount(1);
});

it('mencoba ulang 2x untuk 5xx lalu melempar MidtransUnavailableException', function (): void {
    Http::fake([
        'api.sandbox.midtrans.com/*' => Http::response(['status_message' => 'Internal error'], 503),
    ]);

    expect(fn () => midtransTestClient()->getStatus('INV-1'))
        ->toThrow(MidtransUnavailableException::class);

    Http::assertSentCount(MidtransClient::MAX_ATTEMPTS);
});

it('berhasil bila 5xx pulih pada percobaan ulang', function (): void {
    Http::fakeSequence('api.sandbox.midtrans.com/*')
        ->push(['status_message' => 'busy'], 502)
        ->push(['status_code' => '200', 'transaction_status' => 'pending']);

    expect(midtransTestClient()->getStatus('INV-1')['transaction_status'])->toBe('pending');
    Http::assertSentCount(2);
});

it('melempar MidtransUnavailableException saat koneksi timeout tanpa membocorkan URL atau kunci', function (): void {
    Http::fake(['*' => Http::failedConnection('cURL error 28: Operation timed out')]);

    try {
        midtransTestClient()->getStatus('INV-1');
        $this->fail('Seharusnya melempar MidtransUnavailableException.');
    } catch (MidtransUnavailableException $e) {
        expect($e->getMessage())->not->toContain('SB-Mid-server-KLIEN-UJI')
            ->and($e->getMessage())->not->toContain('api.sandbox.midtrans.com');
    }
});

it('membedakan transaksi tidak dikenal (status_code 404 di body)', function (): void {
    Http::fake([
        'api.sandbox.midtrans.com/*' => Http::response(['status_code' => '404', 'status_message' => "Transaction doesn't exist."]),
    ]);

    expect(fn () => midtransTestClient()->getStatus('INV-HILANG'))
        ->toThrow(MidtransTransactionNotFoundException::class);
});

it('mengikat MidtransSignature dan MidtransClient dari config di container', function (): void {
    $signature = app(MidtransSignature::class);
    $payload = ['order_id' => 'INV-1', 'status_code' => '200', 'gross_amount' => '1000.00'];
    $payload['signature_key'] = hash('sha512', 'INV-12001000.00SB-Mid-server-KLIEN-UJI');

    expect($signature->isValid($payload))->toBeTrue()
        ->and(app(MidtransClient::class)->snapJsUrl())->toBe('https://app.sandbox.midtrans.com/snap/snap.js');
});

it('mengembalikan status expire/pending walau status_code di body bukan 200', function (): void {
    Http::fake([
        'api.sandbox.midtrans.com/v2/INV-EXP/status' => Http::response(['status_code' => '407', 'transaction_status' => 'expire', 'gross_amount' => '1000.00']),
        'api.sandbox.midtrans.com/v2/INV-PEND/status' => Http::response(['status_code' => '201', 'transaction_status' => 'pending', 'gross_amount' => '1000.00']),
    ]);

    expect(midtransTestClient()->getStatus('INV-EXP')['transaction_status'])->toBe('expire')
        ->and(midtransTestClient()->getStatus('INV-PEND')['transaction_status'])->toBe('pending');
});
