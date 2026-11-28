<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status verifikasi bukti transfer yang diunggah wali (T-616).
 */
enum TransferProofStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu verifikasi',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
        };
    }
}
