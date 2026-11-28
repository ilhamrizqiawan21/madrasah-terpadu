<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentOrderPurpose;
use App\Enums\PaymentOrderStatus;
use App\Enums\StudentStatus;
use App\Exceptions\Payments\MidtransException;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\PaymentOrder;
use App\Models\PaymentOrderItem;
use App\Models\Student;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Membuat order Midtrans Snap untuk wali (T-702, PAYMENTS §3).
 *
 * Nominal selalu dihitung dari database; browser hanya mengirim id tagihan atau nominal top-up.
 * Status order setelah dibuat hanya diubah oleh webhook (MidtransNotificationProcessor).
 */
final class PaymentOrderService
{
    private const int ITEM_NAME_MAX = 50;

    public function __construct(
        private readonly MidtransClient $midtrans,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  list<int>  $invoiceIds
     *
     * @throws ModelNotFoundException bila wali tidak terhubung ke siswa atau tagihan bukan milik siswa itu.
     */
    public function createForInvoices(Guardian $guardian, Student $student, array $invoiceIds): PaymentOrder
    {
        $this->assertGuardianOwnsStudent($guardian, $student);

        $invoiceIds = array_values(array_unique(array_map(intval(...), $invoiceIds)));
        sort($invoiceIds);

        if ($invoiceIds === []) {
            throw ValidationException::withMessages(['invoice_ids' => 'Pilih minimal satu tagihan untuk dibayar.']);
        }

        $order = DB::transaction(function () use ($guardian, $student, $invoiceIds): PaymentOrder {
            // Kunci baris tagihan agar dua wali yang membayar bersamaan tidak membuat dua order untuk tagihan yang sama.
            $invoices = Invoice::query()
                ->whereIn('id', $invoiceIds)
                ->where('student_id', $student->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($invoices->count() !== count($invoiceIds)) {
                throw (new ModelNotFoundException)->setModel(Invoice::class, $invoiceIds);
            }

            foreach ($invoices as $invoice) {
                if ($invoice->status !== InvoiceStatus::Unpaid || $invoice->remaining() <= 0) {
                    throw ValidationException::withMessages([
                        'invoice_ids' => "Tagihan {$invoice->description} tidak dapat dibayar online. Tagihan yang sudah dicicil dilunasi di kasir sekolah.",
                    ]);
                }
            }

            $busy = PaymentOrderItem::query()
                ->whereIn('invoice_id', $invoiceIds)
                ->whereHas('order', static fn ($query) => $query
                    ->where('status', PaymentOrderStatus::Pending->value)
                    ->where('expires_at', '>', now()))
                ->exists();

            if ($busy) {
                throw ValidationException::withMessages([
                    'invoice_ids' => 'Sebagian tagihan sedang menunggu pembayaran di order lain. Selesaikan atau tunggu order itu kedaluwarsa.',
                ]);
            }

            $order = $this->newOrder($guardian, $student, PaymentOrderPurpose::Invoices, (int) $invoices->sum(
                static fn (Invoice $invoice): int => $invoice->remaining(),
            ));

            foreach ($invoices as $invoice) {
                PaymentOrderItem::query()->create([
                    'payment_order_id' => $order->id,
                    'invoice_id' => $invoice->id,
                    'description' => Str::limit($invoice->description, 100, ''),
                    'amount' => $invoice->remaining(),
                ]);
            }

            $this->audit->record('payment_order.created', $order, null, [
                'order_id' => $order->order_id,
                'purpose' => $order->purpose->value,
                'student_id' => $student->id,
                'gross_amount' => $order->gross_amount,
                'invoice_ids' => $invoiceIds,
            ]);

            return $order;
        });

        return $this->requestSnapToken($order, $guardian);
    }

    public function createForTopUp(Guardian $guardian, Student $student, int $amount): PaymentOrder
    {
        $this->assertGuardianOwnsStudent($guardian, $student);

        $min = (int) config('sekolah.wallet.min_top_up', 10_000);
        $max = (int) config('sekolah.wallet.max_top_up', 1_000_000);

        if ($amount < $min || $amount > $max) {
            throw ValidationException::withMessages([
                'amount' => 'Nominal isi saldo harus antara Rp '.number_format($min, 0, ',', '.').' dan Rp '.number_format($max, 0, ',', '.').'.',
            ]);
        }

        if ($student->status !== StudentStatus::Active) {
            throw ValidationException::withMessages(['amount' => 'Saldo kantin hanya dapat diisi untuk siswa aktif.']);
        }

        $order = DB::transaction(function () use ($guardian, $student, $amount): PaymentOrder {
            $order = $this->newOrder($guardian, $student, PaymentOrderPurpose::WalletTopup, $amount);

            PaymentOrderItem::query()->create([
                'payment_order_id' => $order->id,
                'invoice_id' => null,
                'description' => Str::limit('Isi saldo kantin '.($student->nickname ?? $student->name), 100, ''),
                'amount' => $amount,
            ]);

            $this->audit->record('payment_order.created', $order, null, [
                'order_id' => $order->order_id,
                'purpose' => $order->purpose->value,
                'student_id' => $student->id,
                'gross_amount' => $amount,
            ]);

            return $order;
        });

        return $this->requestSnapToken($order, $guardian);
    }

    /**
     * Menyusun body Snap dari order yang tersimpan (PAYMENTS §3 langkah 4).
     *
     * @return array<string, mixed>
     */
    public function snapPayload(PaymentOrder $order, Guardian $guardian): array
    {
        $order->loadMissing('items');

        $items = $order->items
            ->sortBy('id')
            ->map(static fn (PaymentOrderItem $item): array => [
                'id' => $item->invoice_id !== null ? "invoice-{$item->invoice_id}" : 'wallet-topup',
                'price' => $item->amount,
                'quantity' => 1,
                'name' => Str::limit($item->description, self::ITEM_NAME_MAX, ''),
            ])
            ->values()
            ->all();

        $hours = (int) config('sekolah.payments.order_expiry_hours', 24);

        return [
            'transaction_details' => [
                'order_id' => $order->order_id,
                'gross_amount' => $order->gross_amount,
            ],
            'item_details' => $items,
            'customer_details' => [
                'first_name' => Str::limit($guardian->name, 50, ''),
                'phone' => $guardian->phone,
            ],
            'enabled_payments' => array_values((array) config('sekolah.payments.enabled_payments', [])),
            'expiry' => ['unit' => 'hours', 'duration' => $hours],
            'callbacks' => [
                'finish' => route('guardian.payments.finish', $order),
            ],
        ];
    }

    private function newOrder(Guardian $guardian, Student $student, PaymentOrderPurpose $purpose, int $grossAmount): PaymentOrder
    {
        if ($grossAmount <= 0) {
            throw ValidationException::withMessages(['invoice_ids' => 'Tidak ada sisa tagihan yang perlu dibayar.']);
        }

        return PaymentOrder::query()->create([
            'order_id' => $purpose->orderPrefix().Str::ulid()->toBase32(),
            'purpose' => $purpose,
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
            'gross_amount' => $grossAmount,
            'status' => PaymentOrderStatus::Pending,
            'expires_at' => now()->addHours((int) config('sekolah.payments.order_expiry_hours', 24)),
        ]);
    }

    /**
     * Dipanggil setelah transaksi DB selesai: panggilan HTTP tidak boleh menahan kunci baris tagihan.
     */
    private function requestSnapToken(PaymentOrder $order, Guardian $guardian): PaymentOrder
    {
        try {
            $snap = $this->midtrans->createSnapTransaction($this->snapPayload($order, $guardian));
        } catch (MidtransException $e) {
            // Order gagal membebaskan tagihan untuk dicoba lagi; status ini bukan hasil pembayaran.
            $order->forceFill(['status' => PaymentOrderStatus::Failed])->save();
            $this->audit->record('payment_order.snap_failed', $order, null, ['order_id' => $order->order_id]);
            Log::warning('Snap Midtrans gagal dibuat.', ['order_id' => $order->order_id, 'error' => $e->getMessage()]);

            // Kunci galat mengikuti form pemanggil: portal tagihan membaca `payment`, form isi saldo `amount`.
            $field = $order->purpose === PaymentOrderPurpose::WalletTopup ? 'amount' : 'payment';

            throw ValidationException::withMessages([
                $field => 'Gagal menghubungi layanan pembayaran. Silakan coba lagi beberapa saat lagi.',
            ]);
        }

        $order->forceFill([
            'snap_token' => $snap['token'],
            'snap_redirect_url' => $snap['redirect_url'],
        ])->save();

        return $order;
    }

    private function assertGuardianOwnsStudent(Guardian $guardian, Student $student): void
    {
        if (! $guardian->students()->whereKey($student->id)->exists()) {
            throw (new ModelNotFoundException)->setModel(Student::class, [$student->id]);
        }
    }
}
