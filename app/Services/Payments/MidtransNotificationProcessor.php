<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Domain\Billing\InvoiceStatusResolver;
use App\Domain\Payments\MidtransOutcome;
use App\Domain\Payments\MidtransStatusMapper;
use App\Enums\PaymentMethod;
use App\Enums\PaymentOrderPurpose;
use App\Enums\PaymentOrderStatus;
use App\Enums\PaymentReviewReason;
use App\Enums\Role;
use App\Exceptions\Payments\MidtransTransactionNotFoundException;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentNotification;
use App\Models\PaymentOrder;
use App\Models\PaymentOrderItem;
use App\Models\User;
use App\Notifications\InvoicePaidNotification;
use App\Notifications\PaymentReviewRequiredNotification;
use App\Notifications\WalletTopupNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\ReceiptNumberSequence;
use App\Services\Notifications\GuardianNotifier;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Memproses notifikasi Midtrans yang lolos signature (T-703, PAYMENTS §4).
 *
 * Isi webhook tidak dipercaya: status dan nominal selalu diambil ulang dari Get Status di dalam
 * transaksi yang mengunci order, sehingga notifikasi ganda atau bersamaan hanya membayar sekali.
 */
final class MidtransNotificationProcessor
{
    public function __construct(
        private readonly MidtransClient $midtrans,
        private readonly AuditLogger $audit,
        private readonly GuardianNotifier $notifier,
        private readonly WalletService $wallets,
        private readonly ReceiptNumberSequence $receipts,
    ) {}

    /**
     * Memproses satu baris notifikasi. Melempar exception bila Midtrans tidak dapat dihubungi
     * sehingga job diulang antrean; tidak ada perubahan yang tersimpan sebelum itu.
     */
    public function process(PaymentNotification $notification): string
    {
        if ($notification->processed_at !== null) {
            return (string) $notification->process_result;
        }

        $orderId = (string) $notification->order_id;

        $result = DB::transaction(function () use ($orderId): string {
            /** @var PaymentOrder|null $order */
            $order = PaymentOrder::query()->where('order_id', $orderId)->lockForUpdate()->first();

            if ($order === null) {
                return 'unknown_order';
            }

            return $this->reconcile($order);
        });

        $notification->forceFill([
            'processed_at' => now(),
            'process_result' => $result,
        ])->save();

        return $result;
    }

    /**
     * Mencocokkan satu order dengan Get Status. Dipakai webhook, job kedaluwarsa, dan rekonsiliasi.
     * Wajib dipanggil di dalam transaksi dengan order yang sudah dikunci.
     */
    public function reconcile(PaymentOrder $order): string
    {
        try {
            $status = $this->midtrans->getStatus($order->order_id);
        } catch (MidtransTransactionNotFoundException) {
            // Wali belum memilih metode bayar di Snap: belum ada transaksi di Midtrans.
            return 'not_found';
        }

        $outcome = MidtransStatusMapper::map(
            is_string($status['transaction_status'] ?? null) ? $status['transaction_status'] : null,
            is_string($status['fraud_status'] ?? null) ? $status['fraud_status'] : null,
        );

        $order->forceFill(['last_status_payload' => $this->safePayload($status)]);

        if ($outcome === MidtransOutcome::Paid || $outcome === MidtransOutcome::Challenge) {
            $grossAmount = MidtransStatusMapper::grossAmountToInt($status['gross_amount'] ?? null);

            if ($grossAmount !== $order->gross_amount) {
                $order->save();
                $this->flagForReview($order, PaymentReviewReason::AmountMismatch, sprintf(
                    'Nominal dari Midtrans %s tidak sama dengan nominal order. Tagihan tidak ditandai lunas.',
                    is_scalar($status['gross_amount'] ?? null) ? (string) $status['gross_amount'] : '(kosong)',
                ));

                return 'amount_mismatch';
            }
        }

        return match ($outcome) {
            MidtransOutcome::Paid => $this->markPaid($order, $status),
            MidtransOutcome::Challenge => $this->holdChallenge($order),
            MidtransOutcome::Expired => $this->transition($order, PaymentOrderStatus::Expired, 'expired'),
            MidtransOutcome::Failed => $this->transition($order, PaymentOrderStatus::Failed, 'failed'),
            MidtransOutcome::NeedsReview => $this->needsReview($order, $status),
            MidtransOutcome::Pending => $this->keep($order, 'pending'),
            MidtransOutcome::Unknown => $this->keep($order, 'unknown_status'),
        };
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function markPaid(PaymentOrder $order, array $status): string
    {
        if ($order->status === PaymentOrderStatus::Paid) {
            $order->save();

            return 'duplicate';
        }

        // Pembayaran yang tiba setelah order kedaluwarsa tetap sah (PAYMENTS §4 langkah 5).
        if (! in_array($order->status, [PaymentOrderStatus::Pending, PaymentOrderStatus::Expired], true)) {
            $order->save();
            $this->flagForReview($order, PaymentReviewReason::NeedsReview, "Midtrans melaporkan lunas untuk order berstatus {$order->status->label()}. Periksa dasbor Midtrans.");

            return 'invalid_transition';
        }

        $order->forceFill([
            'status' => PaymentOrderStatus::Paid,
            'paid_at' => now(),
            'payment_type' => is_string($status['payment_type'] ?? null) ? $status['payment_type'] : null,
            'midtrans_transaction_id' => is_string($status['transaction_id'] ?? null) ? $status['transaction_id'] : null,
        ])->save();

        $this->audit->record('payment_order.paid', $order, null, [
            'order_id' => $order->order_id,
            'gross_amount' => $order->gross_amount,
            'payment_type' => $order->payment_type,
        ]);

        return match ($order->purpose) {
            PaymentOrderPurpose::Invoices => $this->settleInvoices($order),
            PaymentOrderPurpose::WalletTopup => $this->settleTopUp($order),
        };
    }

    private function settleInvoices(PaymentOrder $order): string
    {
        $order->loadMissing(['items', 'student']);

        $invoiceIds = $order->items->pluck('invoice_id')->filter()->map(intval(...))->sort()->values()->all();

        $invoices = Invoice::query()
            ->whereIn('id', $invoiceIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $allocations = [];
        $overpaid = 0;

        foreach ($order->items->sortBy('id') as $item) {
            /** @var PaymentOrderItem $item */
            $invoice = $item->invoice_id !== null ? $invoices->get($item->invoice_id) : null;
            $allocatable = $invoice instanceof Invoice ? min($item->amount, $invoice->remaining()) : 0;

            if ($allocatable > 0) {
                $allocations[$item->invoice_id] = $allocatable;
            }

            $overpaid += $item->amount - $allocatable;
        }

        $allocatedTotal = array_sum($allocations);

        if ($allocatedTotal > 0) {
            $now = now();
            $payment = Payment::query()->create([
                'student_id' => $order->student_id,
                'method' => PaymentMethod::Midtrans,
                'amount' => $allocatedTotal,
                'paid_at' => $now,
                'received_by' => null,
                'payment_order_id' => $order->id,
                'reference' => $order->order_id,
                'receipt_number' => $this->receipts->next((int) $now->timezone('Asia/Makassar')->year),
                'created_at' => $now,
            ]);

            foreach ($allocations as $invoiceId => $amount) {
                /** @var Invoice $invoice */
                $invoice = $invoices->get($invoiceId);

                PaymentAllocation::query()->create([
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $amount,
                ]);

                $paidTotal = $invoice->paid_total + $amount;
                $invoice->update([
                    'paid_total' => $paidTotal,
                    'status' => InvoiceStatusResolver::resolve($invoice->amount, $invoice->adjustment_total, $paidTotal),
                ]);
            }

            $this->audit->record('payment.received', $payment, null, [
                'student_id' => $order->student_id,
                'method' => PaymentMethod::Midtrans->value,
                'amount' => $allocatedTotal,
                'receipt_number' => $payment->receipt_number,
                'order_id' => $order->order_id,
                'allocations' => $allocations,
            ]);

            $this->notifier->notifyStudentGuardians($order->student, new InvoicePaidNotification(
                paymentId: $payment->id,
                studentName: $order->student->nickname ?? $order->student->name,
                amount: $allocatedTotal,
                receiptNumber: $payment->receipt_number,
            ));
        }

        if ($overpaid > 0) {
            $this->handleOverpayment($order, $overpaid);

            return 'paid_overpaid';
        }

        return 'paid';
    }

    /**
     * Tagihan sudah dilunasi kasir di sela waktu: kelebihan menjadi saldo kantin (ADR-009, default U-05).
     */
    private function handleOverpayment(PaymentOrder $order, int $overpaid): void
    {
        $credited = false;

        if ((bool) config('sekolah.payments.overpayment_to_wallet', true)) {
            try {
                $this->wallets->adjust(
                    student: $order->student,
                    signedAmount: $overpaid,
                    idempotencyKey: "midtrans-overpaid:{$order->order_id}",
                    actor: null,
                    reason: "Kelebihan bayar online {$order->order_id}",
                    referenceType: PaymentOrder::class,
                    referenceId: $order->id,
                );
                $credited = true;
            } catch (ValidationException) {
                // Siswa tidak aktif tanpa dompet: biarkan bendahara memutuskan pengembalian.
                $credited = false;
            }
        }

        $this->flagForReview($order, PaymentReviewReason::Overpaid, $credited
            ? 'Tagihan sudah lunas lebih dulu; kelebihan dicatat sebagai saldo kantin siswa.'
            : 'Tagihan sudah lunas lebih dulu; kelebihan belum dikembalikan dan perlu diputuskan bendahara.', $overpaid);
    }

    private function settleTopUp(PaymentOrder $order): string
    {
        $order->loadMissing('student');

        try {
            $entry = $this->wallets->topUp(
                student: $order->student,
                amount: $order->gross_amount,
                idempotencyKey: "midtrans:{$order->order_id}",
                actor: null,
                description: 'Isi saldo online',
                referenceType: PaymentOrder::class,
                referenceId: $order->id,
            );
        } catch (ValidationException) {
            $this->flagForReview($order, PaymentReviewReason::NeedsReview, 'Isi saldo berhasil dibayar tetapi dompet siswa tidak dapat diisi (siswa tidak aktif).');

            return 'paid_needs_review';
        }

        $this->notifier->notifyStudentGuardians($order->student, new WalletTopupNotification(
            entryId: $entry->id,
            studentName: $order->student->nickname ?? $order->student->name,
            amount: $order->gross_amount,
            balanceAfter: $entry->balance_after,
        ));

        return 'paid';
    }

    private function holdChallenge(PaymentOrder $order): string
    {
        $order->save();

        if ($order->status === PaymentOrderStatus::Pending && $order->review_reason !== PaymentReviewReason::Challenge) {
            $this->flagForReview($order, PaymentReviewReason::Challenge, 'Transaksi kartu ditahan fraud check Midtrans. Setujui atau tolak di dasbor Midtrans.');
        }

        return 'challenge';
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function needsReview(PaymentOrder $order, array $status): string
    {
        $order->save();
        $transactionStatus = is_string($status['transaction_status'] ?? null) ? $status['transaction_status'] : '-';
        $this->flagForReview($order, PaymentReviewReason::NeedsReview, "Midtrans melaporkan {$transactionStatus}. Tidak diproses otomatis.");

        return 'needs_review';
    }

    private function transition(PaymentOrder $order, PaymentOrderStatus $to, string $result): string
    {
        // Order lunas tidak pernah mundur; expire/failed hanya berlaku untuk order pending.
        if ($order->status !== PaymentOrderStatus::Pending) {
            $order->save();

            return 'ignored';
        }

        $before = $order->status->value;
        $order->forceFill(['status' => $to])->save();
        $this->audit->record("payment_order.{$to->value}", $order, ['status' => $before], ['status' => $to->value]);

        return $result;
    }

    private function keep(PaymentOrder $order, string $result): string
    {
        $order->save();

        return $result;
    }

    private function flagForReview(PaymentOrder $order, PaymentReviewReason $reason, string $detail, ?int $amount = null): void
    {
        $order->forceFill([
            'review_reason' => $reason,
            'reviewed_at' => null,
            'reviewed_by' => null,
        ])->save();

        $this->audit->record('payment_order.flagged', $order, null, [
            'order_id' => $order->order_id,
            'reason' => $reason->value,
        ]);

        Log::warning('Order Midtrans perlu ditinjau.', ['order_id' => $order->order_id, 'reason' => $reason->value]);

        $order->loadMissing('student');
        $treasurers = User::role(Role::Treasurer->value)->where('is_active', true)->orderBy('id')->get();

        if ($treasurers->isNotEmpty()) {
            $notification = new PaymentReviewRequiredNotification(
                orderId: $order->order_id,
                reason: $reason,
                studentName: $order->student->name,
                amount: $amount ?? $order->gross_amount,
                detail: $detail,
            );

            DB::afterCommit(static fn () => Notification::send($treasurers, $notification));
        }
    }

    /**
     * Respons Get Status tidak memuat kunci; buang `signature_key` dan data kartu agar aman ditampilkan.
     *
     * @param  array<string, mixed>  $status
     * @return array<string, mixed>
     */
    private function safePayload(array $status): array
    {
        return array_diff_key($status, array_flip(['signature_key', 'masked_card', 'card_token', 'saved_token_id']));
    }
}
