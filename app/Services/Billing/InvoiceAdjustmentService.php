<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Domain\Billing\InvoiceStatusResolver;
use App\Enums\InvoiceAdjustmentType;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceAdjustment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Penyesuaian append-only dan pembatalan tagihan (T-604).
 */
final class InvoiceAdjustmentService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function adjust(
        User $actor,
        Invoice $invoice,
        InvoiceAdjustmentType $type,
        int $amount,
        string $reason,
    ): InvoiceAdjustment {
        $cleanReason = trim($reason);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Nominal penyesuaian harus lebih dari nol.',
            ]);
        }

        if ($cleanReason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan penyesuaian wajib diisi.',
            ]);
        }

        return DB::transaction(function () use ($actor, $invoice, $type, $amount, $cleanReason): InvoiceAdjustment {
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === InvoiceStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'invoice' => 'Tagihan yang sudah dibatalkan tidak dapat disesuaikan.',
                ]);
            }

            $remaining = $locked->remaining();
            if ($amount > $remaining) {
                throw ValidationException::withMessages([
                    'amount' => 'Nominal penyesuaian melebihi sisa tagihan.',
                ]);
            }

            $before = $locked->only(['adjustment_total', 'status']);

            $adjustment = InvoiceAdjustment::query()->create([
                'invoice_id' => $locked->id,
                'type' => $type->value,
                'amount' => $amount,
                'reason' => $cleanReason,
                'created_by' => $actor->id,
                'created_at' => now(),
            ]);

            $newAdjustmentTotal = $locked->adjustment_total + $amount;
            $newStatus = InvoiceStatusResolver::resolve(
                $locked->amount,
                $newAdjustmentTotal,
                $locked->paid_total,
            );

            $locked->update([
                'adjustment_total' => $newAdjustmentTotal,
                'status' => $newStatus,
            ]);

            $this->audit->record(
                action: 'invoice.adjusted',
                subject: $locked,
                before: $before,
                after: [
                    'adjustment_total' => $newAdjustmentTotal,
                    'status' => $newStatus->value,
                    'adjustment_id' => $adjustment->id,
                    'type' => $type->value,
                    'amount' => $amount,
                    'reason' => $cleanReason,
                ],
                userId: $actor->id,
            );

            return $adjustment;
        });
    }

    public function cancel(User $actor, Invoice $invoice, string $reason): Invoice
    {
        $cleanReason = trim($reason);

        if ($cleanReason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan pembatalan wajib diisi.',
            ]);
        }

        return DB::transaction(function () use ($actor, $invoice, $cleanReason): Invoice {
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === InvoiceStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'invoice' => 'Tagihan sudah dibatalkan.',
                ]);
            }

            if ($locked->paid_total > 0) {
                throw ValidationException::withMessages([
                    'invoice' => 'Tagihan yang sudah memiliki pembayaran tidak dapat dibatalkan.',
                ]);
            }

            $before = $locked->only(['status', 'cancelled_reason']);

            $locked->update([
                'status' => InvoiceStatus::Cancelled,
                'cancelled_reason' => $cleanReason,
            ]);

            $this->audit->record(
                action: 'invoice.cancelled',
                subject: $locked,
                before: $before,
                after: $locked->only(['status', 'cancelled_reason']),
                userId: $actor->id,
            );

            return $locked;
        });
    }
}
