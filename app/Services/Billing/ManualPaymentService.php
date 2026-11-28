<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Domain\Billing\InvoiceStatusResolver;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Student;
use App\Models\User;
use App\Notifications\InvoicePaidNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\GuardianNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Penerimaan pembayaran kasir manual (tunai/transfer, boleh cicilan) dengan nomor kuitansi berurutan per tahun (T-605).
 */
final class ManualPaymentService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly GuardianNotifier $notifier,
        private readonly ReceiptNumberSequence $receipts,
    ) {}

    /**
     * @param  list<array{invoice_id: int, amount: int}>  $allocations
     */
    public function receive(
        User $actor,
        Student $student,
        PaymentMethod $method,
        array $allocations,
        ?string $reference = null,
    ): Payment {
        if (! in_array($method, [PaymentMethod::Cash, PaymentMethod::Transfer], true)) {
            throw ValidationException::withMessages([
                'method' => 'Kasir hanya menerima pembayaran tunai atau transfer.',
            ]);
        }

        if ($allocations === []) {
            throw ValidationException::withMessages([
                'allocations' => 'Pilih minimal satu tagihan untuk dibayar.',
            ]);
        }

        $normalized = [];
        foreach ($allocations as $row) {
            $invoiceId = (int) $row['invoice_id'];
            $amount = (int) $row['amount'];
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'allocations' => 'Nominal alokasi tiap tagihan harus lebih dari nol.',
                ]);
            }
            $normalized[$invoiceId] = ($normalized[$invoiceId] ?? 0) + $amount;
        }

        ksort($normalized);
        $invoiceIds = array_keys($normalized);
        $cleanReference = $reference !== null && trim($reference) !== '' ? trim($reference) : null;

        $payment = DB::transaction(function () use ($actor, $student, $method, $normalized, $invoiceIds, $cleanReference): Payment {
            $invoices = Invoice::query()
                ->whereIn('id', $invoiceIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($invoices->count() !== count($invoiceIds)) {
                throw ValidationException::withMessages([
                    'allocations' => 'Sebagian tagihan tidak ditemukan.',
                ]);
            }

            $totalPayment = 0;

            foreach ($normalized as $invoiceId => $allocAmount) {
                /** @var Invoice $invoice */
                $invoice = $invoices->get($invoiceId);

                if ($invoice->student_id !== $student->id) {
                    throw ValidationException::withMessages([
                        'allocations' => 'Tagihan tidak sesuai dengan siswa yang dipilih.',
                    ]);
                }

                if ($invoice->status === InvoiceStatus::Cancelled) {
                    throw ValidationException::withMessages([
                        'allocations' => 'Tagihan yang dibatalkan tidak dapat dibayar.',
                    ]);
                }

                if ($allocAmount > $invoice->remaining()) {
                    throw ValidationException::withMessages([
                        'allocations' => "Nominal pembayaran untuk {$invoice->description} melebihi sisa tagihan.",
                    ]);
                }

                $totalPayment += $allocAmount;
            }

            $now = now();
            $year = (int) $now->timezone('Asia/Makassar')->year;
            $receiptNumber = $this->receipts->next($year);

            $payment = Payment::query()->create([
                'student_id' => $student->id,
                'method' => $method->value,
                'amount' => $totalPayment,
                'paid_at' => $now,
                'received_by' => $actor->id,
                'reference' => $cleanReference,
                'receipt_number' => $receiptNumber,
                'created_at' => $now,
            ]);

            foreach ($normalized as $invoiceId => $allocAmount) {
                /** @var Invoice $invoice */
                $invoice = $invoices->get($invoiceId);

                PaymentAllocation::query()->create([
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $allocAmount,
                ]);

                $newPaidTotal = $invoice->paid_total + $allocAmount;
                $newStatus = InvoiceStatusResolver::resolve(
                    $invoice->amount,
                    $invoice->adjustment_total,
                    $newPaidTotal,
                );

                $invoice->update([
                    'paid_total' => $newPaidTotal,
                    'status' => $newStatus,
                ]);
            }

            $this->audit->record(
                action: 'payment.received',
                subject: $payment,
                after: [
                    'student_id' => $student->id,
                    'method' => $method->value,
                    'amount' => $totalPayment,
                    'receipt_number' => $receiptNumber,
                    'allocations' => $normalized,
                ],
                userId: $actor->id,
            );

            return $payment;
        });

        $this->notifier->notifyStudentGuardians(
            $student,
            new InvoicePaidNotification(
                paymentId: $payment->id,
                studentName: $student->nickname ?? $student->name,
                amount: $payment->amount,
                receiptNumber: $payment->receipt_number,
            ),
        );

        return $payment;
    }
}
