<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Alasan order masuk antrean "Perlu ditinjau" bendahara (PAYMENTS §4, ADR-009).
 */
enum PaymentReviewReason: string
{
    case AmountMismatch = 'amount_mismatch';
    case NeedsReview = 'needs_review';
    case Overpaid = 'overpaid';
    case Challenge = 'challenge';

    public function label(): string
    {
        return match ($this) {
            self::AmountMismatch => 'Nominal tidak cocok',
            self::NeedsReview => 'Refund/chargeback',
            self::Overpaid => 'Kelebihan bayar',
            self::Challenge => 'Ditahan fraud check',
        };
    }
}
