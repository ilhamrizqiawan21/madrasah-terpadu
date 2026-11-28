<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Domain\Billing\InvoiceStatusResolver;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pembalikan pembayaran manual (kasir) dengan pengembalian status tagihan dan audit (T-606).
 */
final class PaymentReversalService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function reverse(User $actor, Payment $payment, string $reason): Payment
    {
        $cleanReason = trim($reason);

        if ($cleanReason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan pembalikan pembayaran wajib diisi.',
            ]);
        }

        return DB::transaction(function () use ($actor, $payment, $cleanReason): Payment {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->method === PaymentMethod::Midtrans) {
                throw ValidationException::withMessages([
                    'payment' => 'Pembayaran Midtrans tidak dapat dibalik dari sini. Silakan proses melalui dashboard Midtrans.',
                ]);
            }

            if ($locked->reversed_at !== null) {
                throw ValidationException::withMessages([
                    'payment' => 'Pembayaran ini sudah pernah dibalik.',
                ]);
            }

            $allocations = $locked->allocations()->orderBy('invoice_id')->get();
            $invoiceIds = $allocations->pluck('invoice_id')->all();

            $invoices = $invoiceIds === []
                ? collect()
                : Invoice::query()
                    ->whereIn('id', $invoiceIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

            foreach ($allocations as $alloc) {
                /** @var Invoice|null $invoice */
                $invoice = $invoices->get($alloc->invoice_id);
                if ($invoice === null) {
                    continue;
                }

                $newPaidTotal = max(0, $invoice->paid_total - $alloc->amount);
                $newStatus = InvoiceStatusResolver::resolve(
                    $invoice->amount,
                    $invoice->adjustment_total,
                    $newPaidTotal,
                    $invoice->status === InvoiceStatus::Cancelled,
                );

                $invoice->update([
                    'paid_total' => $newPaidTotal,
                    'status' => $newStatus,
                ]);
            }

            $before = $locked->only(['reversed_at', 'reversal_reason', 'reversed_by']);
            $now = now();

            $locked->update([
                'reversed_at' => $now,
                'reversal_reason' => $cleanReason,
                'reversed_by' => $actor->id,
            ]);

            $this->audit->record(
                action: 'payment.reversed',
                subject: $locked,
                before: $before,
                after: [
                    'reversed_at' => $now->toIso8601String(),
                    'reversal_reason' => $cleanReason,
                    'reversed_by' => $actor->id,
                    'receipt_number' => $locked->receipt_number,
                    'amount' => $locked->amount,
                ],
                userId: $actor->id,
            );

            return $locked;
        });
    }
}
