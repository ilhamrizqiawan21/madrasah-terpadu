<?php

declare(strict_types=1);

namespace App\Domain\Payments;

/**
 * Arti status Midtrans bagi order (PAYMENTS §4 langkah 4).
 */
enum MidtransOutcome: string
{
    case Paid = 'paid';
    case Pending = 'pending';
    /** capture + fraud_status challenge: order tetap pending, bendahara memeriksa di dasbor Midtrans. */
    case Challenge = 'challenge';
    case Expired = 'expired';
    case Failed = 'failed';
    /** refund, partial_refund, chargeback: tidak diotomatisasi. */
    case NeedsReview = 'needs_review';
    /** Status yang tidak dikenal: tidak mengubah apa pun. */
    case Unknown = 'unknown';
}
