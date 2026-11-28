<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Exceptions\Payments\MidtransRequestException;
use App\Exceptions\Payments\MidtransTransactionNotFoundException;
use App\Exceptions\Payments\MidtransUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Klien HTTP Midtrans (T-701, PAYMENTS §2–3, ADR-007): Snap untuk membuat transaksi, Core API untuk Get Status.
 *
 * Server Key hanya dipakai sebagai Basic Auth dan tidak pernah masuk pesan exception atau log.
 */
final class MidtransClient
{
    public const int TIMEOUT_SECONDS = 10;

    /** Percobaan total = 1 + 2 ulangan, hanya untuk 5xx dan kegagalan koneksi. */
    public const int MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly string $serverKey,
        private readonly bool $isProduction,
        private readonly ?string $notificationUrl = null,
        private readonly int $retrySleepMilliseconds = 200,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  Body Snap yang sudah disusun server (PAYMENTS §3).
     * @return array{token: string, redirect_url: string}
     */
    public function createSnapTransaction(array $payload): array
    {
        $request = $this->request();

        // Akun Midtrans dipakai bersama aplikasi lama: arahkan notifikasi order kita ke aplikasi ini
        // tanpa menyentuh URL notifikasi di dasbor (PAYMENTS §2a).
        if ($this->notificationUrl !== null && $this->notificationUrl !== '') {
            $request = $request->withHeaders(['X-Override-Notification' => $this->notificationUrl]);
        }

        $response = $this->send(fn (): Response => $request->post($this->snapUrl(), $payload));

        $token = $response->json('token');
        $redirectUrl = $response->json('redirect_url');

        if (! is_string($token) || $token === '' || ! is_string($redirectUrl) || $redirectUrl === '') {
            throw new MidtransUnavailableException('respons Snap tidak memuat token pembayaran.');
        }

        return ['token' => $token, 'redirect_url' => $redirectUrl];
    }

    /**
     * @return array<string, mixed> Isi respons Get Status apa adanya.
     *
     * @throws MidtransTransactionNotFoundException bila wali belum memilih metode bayar di Snap.
     */
    public function getStatus(string $orderId): array
    {
        $response = $this->send(fn (): Response => $this->request()->get($this->statusUrl($orderId)));

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];

        // Core API dapat menjawab HTTP 200 dengan `status_code` galat di body.
        $bodyStatus = (int) ($body['status_code'] ?? 200);

        if ($bodyStatus === 404) {
            throw new MidtransTransactionNotFoundException($orderId);
        }

        if ($bodyStatus >= 500) {
            throw new MidtransUnavailableException("Get Status menjawab {$bodyStatus}.");
        }

        if ($bodyStatus >= 400) {
            throw new MidtransRequestException($bodyStatus, $this->messagesFrom($body));
        }

        return $body;
    }

    public function snapJsUrl(): string
    {
        return $this->isProduction
            ? 'https://app.midtrans.com/snap/snap.js'
            : 'https://app.sandbox.midtrans.com/snap/snap.js';
    }

    private function snapUrl(): string
    {
        return $this->isProduction
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    }

    private function statusUrl(string $orderId): string
    {
        $base = $this->isProduction ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';

        return $base.'/v2/'.rawurlencode($orderId).'/status';
    }

    private function request(): PendingRequest
    {
        return Http::withBasicAuth($this->serverKey, '')
            ->acceptJson()
            ->asJson()
            ->timeout(self::TIMEOUT_SECONDS)
            ->retry(
                self::MAX_ATTEMPTS,
                $this->retrySleepMilliseconds,
                static fn (Throwable $e): bool => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->serverError()),
                throw: false,
            );
    }

    /**
     * @param  callable(): Response  $call
     */
    private function send(callable $call): Response
    {
        try {
            $response = $call();
        } catch (ConnectionException) {
            // Pesan ConnectionException bisa memuat URL lengkap; cukup sebut jenis kegagalannya.
            throw new MidtransUnavailableException('waktu habis atau koneksi gagal.');
        }

        if ($response->serverError()) {
            throw new MidtransUnavailableException("HTTP {$response->status()} setelah ".self::MAX_ATTEMPTS.' percobaan.');
        }

        if ($response->clientError()) {
            /** @var array<string, mixed> $body */
            $body = $response->json() ?? [];

            throw new MidtransRequestException($response->status(), $this->messagesFrom($body));
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return list<string>
     */
    private function messagesFrom(array $body): array
    {
        $messages = $body['error_messages'] ?? ($body['status_message'] ?? []);

        if (is_string($messages)) {
            return [$messages];
        }

        if (! is_array($messages)) {
            return [];
        }

        return array_values(array_filter($messages, is_string(...)));
    }
}
