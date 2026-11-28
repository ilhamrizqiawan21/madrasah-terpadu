<?php

declare(strict_types=1);

namespace App\Exceptions\Payments;

/**
 * Midtrans menolak permintaan (4xx): data order salah, order_id ganda, atau kunci tidak valid. Tidak diulang.
 */
class MidtransRequestException extends MidtransException
{
    /**
     * @param  list<string>  $errorMessages  Pesan `error_messages` dari Midtrans (tanpa data rahasia).
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly array $errorMessages = [],
    ) {
        $detail = $errorMessages === [] ? '' : ': '.implode('; ', $errorMessages);

        parent::__construct("Midtrans menolak permintaan (HTTP {$statusCode}){$detail}");
    }
}
