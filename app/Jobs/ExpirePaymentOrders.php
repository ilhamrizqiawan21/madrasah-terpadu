<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PaymentOrderStatus;
use App\Exceptions\Payments\MidtransException;
use App\Models\PaymentOrder;
use App\Services\Audit\AuditLogger;
use App\Services\Payments\MidtransNotificationProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Menutup order Midtrans yang lewat batas waktu (T-704, PAYMENTS §5). Setiap order dicek ke Get Status
 * dulu karena bisa saja sudah dibayar tetapi webhook-nya terlambat atau hilang.
 */
final class ExpirePaymentOrders implements ShouldQueue
{
    use Queueable;

    /** Jeda setelah expires_at agar webhook yang sedang dalam perjalanan sempat diproses lebih dulu. */
    public const int GRACE_MINUTES = 15;

    public function __construct()
    {
        $this->onQueue('payments');
    }

    public function handle(MidtransNotificationProcessor $processor, AuditLogger $audit): int
    {
        $closed = 0;

        PaymentOrder::query()
            ->where('status', PaymentOrderStatus::Pending->value)
            ->where('expires_at', '<', now()->subMinutes(self::GRACE_MINUTES))
            ->orderBy('id')
            ->select('id')
            ->chunkById(100, function ($orders) use ($processor, $audit, &$closed): void {
                foreach ($orders as $row) {
                    try {
                        $closed += $this->closeOne((int) $row->id, $processor, $audit) ? 1 : 0;
                    } catch (MidtransException $e) {
                        // Satu order gagal dicek tidak boleh menghentikan yang lain; dicoba lagi 15 menit kemudian.
                        Log::warning('Cek kedaluwarsa order Midtrans gagal.', ['payment_order_id' => $row->id, 'error' => $e->getMessage()]);
                    }
                }
            });

        return $closed;
    }

    private function closeOne(int $id, MidtransNotificationProcessor $processor, AuditLogger $audit): bool
    {
        return DB::transaction(function () use ($id, $processor, $audit): bool {
            /** @var PaymentOrder|null $order */
            $order = PaymentOrder::query()->whereKey($id)->lockForUpdate()->first();

            if ($order === null || $order->status !== PaymentOrderStatus::Pending) {
                return false;
            }

            $result = $processor->reconcile($order);

            // Midtrans tidak mengenal order ini: wali tidak pernah memilih metode bayar.
            if ($result === 'not_found') {
                $order->forceFill(['status' => PaymentOrderStatus::Expired])->save();
                $audit->record('payment_order.expired', $order, ['status' => PaymentOrderStatus::Pending->value], [
                    'status' => PaymentOrderStatus::Expired->value,
                    'reason' => 'not_found',
                ]);

                return true;
            }

            return $result === 'expired';
        });
    }
}
