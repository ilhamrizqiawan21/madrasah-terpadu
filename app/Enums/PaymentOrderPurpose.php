<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tujuan order Midtrans (PAYMENTS §1): pelunasan tagihan atau isi saldo kantin.
 */
enum PaymentOrderPurpose: string
{
    case Invoices = 'invoices';
    case WalletTopup = 'wallet_topup';

    public function label(): string
    {
        return match ($this) {
            self::Invoices => 'Tagihan',
            self::WalletTopup => 'Isi saldo kantin',
        };
    }

    public function orderPrefix(): string
    {
        return match ($this) {
            self::Invoices => 'INV-',
            self::WalletTopup => 'TOP-',
        };
    }
}
