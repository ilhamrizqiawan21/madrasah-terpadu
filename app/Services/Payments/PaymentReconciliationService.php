<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\PaymentOrderStatus;
use App\Enums\Role;
use App\Exceptions\Payments\MidtransException;
use App\Models\PaymentOrder;
use App\Models\User;
use App\Notifications\PaymentReconciliationNotification;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Rekonsiliasi order Midtrans dengan Get Status (T-705, PAYMENTS §5).
 *
 * Tidak ada koreksi di luar tabel status PAYMENTS §4: perubahan dilakukan pemroses yang sama dengan
 * webhook, dan setiap selisih dilaporkan ke log serta bendahara.
 */
final class PaymentReconciliationService
{
    /** Hasil pemroses yang selalu berarti ada yang perlu dilihat manusia. */
    private const array FLAGGED_RESULTS = ['amount_mismatch', 'needs_review', 'invalid_transition', 'paid_overpaid', 'paid_needs_review'];

    public function __construct(
        private readonly MidtransNotificationProcessor $processor,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{order_id: string, before: string, after: string, result: string}
     */
    public function reconcileOrder(PaymentOrder $order, ?User $actor = null): array
    {
        return DB::transaction(function () use ($order, $actor): array {
            /** @var PaymentOrder $locked */
            $locked = PaymentOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $before = $locked->status;

            $result = $this->processor->reconcile($locked);

            $this->audit->record('payment_order.checked', $locked, ['status' => $before->value], [
                'status' => $locked->status->value,
                'result' => $result,
            ], $actor?->id);

            return [
                'order_id' => $locked->order_id,
                'before' => $before->value,
                'after' => $locked->status->value,
                'result' => $result,
            ];
        });
    }

    /**
     * Mencocokkan semua order yang dibuat pada tanggal (WITA) tersebut.
     *
     * @return array{checked: int, discrepancies: list<array{order_id: string, before: string, after: string, result: string}>, failed: list<string>}
     */
    public function reconcileDate(CarbonImmutable $dateWita): array
    {
        $start = $dateWita->timezone('Asia/Makassar')->startOfDay()->utc();
        $end = $dateWita->timezone('Asia/Makassar')->endOfDay()->utc();

        $checked = 0;
        $discrepancies = [];
        $failed = [];

        PaymentOrder::query()
            ->whereBetween('created_at', [$start, $end])
            ->where('status', '!=', PaymentOrderStatus::Failed->value)
            ->orderBy('id')
            ->chunkById(100, function ($orders) use (&$checked, &$discrepancies, &$failed): void {
                foreach ($orders as $order) {
                    try {
                        $row = $this->reconcileOrder($order);
                    } catch (MidtransException $e) {
                        $failed[] = $order->order_id;
                        Log::warning('Rekonsiliasi order Midtrans gagal.', ['order_id' => $order->order_id, 'error' => $e->getMessage()]);

                        continue;
                    }

                    $checked++;

                    if ($this->isDiscrepancy($row)) {
                        $discrepancies[] = $row;
                    }
                }
            });

        if ($discrepancies !== [] || $failed !== []) {
            Log::warning('Rekonsiliasi Midtrans menemukan selisih.', [
                'date' => $dateWita->toDateString(),
                'discrepancies' => $discrepancies,
                'failed' => $failed,
            ]);

            $treasurers = User::role(Role::Treasurer->value)->where('is_active', true)->orderBy('id')->get();
            if ($treasurers->isNotEmpty()) {
                Notification::send($treasurers, new PaymentReconciliationNotification(
                    date: $dateWita->toDateString(),
                    discrepancies: $discrepancies,
                    failed: $failed,
                ));
            }
        }

        return ['checked' => $checked, 'discrepancies' => $discrepancies, 'failed' => $failed];
    }

    public function markReviewed(PaymentOrder $order, User $actor): PaymentOrder
    {
        return DB::transaction(function () use ($order, $actor): PaymentOrder {
            /** @var PaymentOrder $locked */
            $locked = PaymentOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->review_reason === null || $locked->reviewed_at !== null) {
                throw ValidationException::withMessages(['order' => 'Order ini tidak sedang menunggu tinjauan.']);
            }

            $locked->forceFill(['reviewed_at' => now(), 'reviewed_by' => $actor->id])->save();

            $this->audit->record('payment_order.reviewed', $locked, null, [
                'order_id' => $locked->order_id,
                'reason' => $locked->review_reason->value,
            ], $actor->id);

            return $locked;
        });
    }

    /**
     * @param  array{order_id: string, before: string, after: string, result: string}  $row
     */
    private function isDiscrepancy(array $row): bool
    {
        if ($row['before'] !== $row['after'] || in_array($row['result'], self::FLAGGED_RESULTS, true)) {
            return true;
        }

        // Kita mencatat lunas, tetapi Midtrans tidak lagi melaporkan settlement.
        return $row['before'] === PaymentOrderStatus::Paid->value && $row['result'] !== 'duplicate';
    }
}
