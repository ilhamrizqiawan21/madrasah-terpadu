<?php

declare(strict_types=1);

namespace App\Exceptions\Payments;

use RuntimeException;

/**
 * Dasar semua kegagalan memanggil Midtrans (T-701). Pesan aman ditampilkan dan tidak pernah memuat Server Key.
 */
abstract class MidtransException extends RuntimeException {}
