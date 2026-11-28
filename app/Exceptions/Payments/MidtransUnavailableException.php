<?php

declare(strict_types=1);

namespace App\Exceptions\Payments;

/**
 * Midtrans tidak dapat dihubungi (timeout, koneksi putus) atau terus menjawab 5xx setelah dicoba ulang.
 */
final class MidtransUnavailableException extends MidtransException
{
    public function __construct(string $reason)
    {
        parent::__construct("Layanan Midtrans tidak dapat dihubungi: {$reason}");
    }
}
