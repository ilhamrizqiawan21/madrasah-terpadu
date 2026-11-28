<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentOrderStatus;
use App\Exceptions\Payments\MidtransException;
use App\Http\Controllers\Controller;
use App\Models\PaymentNotification;
use App\Models\PaymentOrder;
use App\Models\PaymentOrderItem;
use App\Models\User;
use App\Services\Payments\PaymentReconciliationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Antrean bendahara "Pembayaran online" (T-705, mockup 06). Status hanya berubah lewat pemroses
 * webhook; tombol di sini hanya meminta pengecekan ulang ke Midtrans atau menandai sudah ditinjau.
 */
class OnlinePaymentController extends Controller
{
    private const array TABS = ['review', 'pending', 'paid', 'closed'];

    /** Kolom Get Status yang aman ditampilkan; sisanya (mis. data kartu) tidak dikirim ke browser. */
    private const array SAFE_PAYLOAD_KEYS = [
        'transaction_status', 'fraud_status', 'status_code', 'status_message', 'payment_type',
        'gross_amount', 'currency', 'transaction_time', 'settlement_time', 'expiry_time', 'transaction_id',
    ];

    public function __construct(
        private readonly PaymentReconciliationService $reconciliation,
    ) {}

    public function index(Request $request): Response
    {
        $tab = in_array($request->string('tab')->toString(), self::TABS, true) ? $request->string('tab')->toString() : 'review';
        $search = trim($request->string('q')->toString());
        $from = $this->parseDate($request->string('from')->toString());
        $to = $this->parseDate($request->string('to')->toString());

        $base = PaymentOrder::query()
            ->when($from !== null, fn (Builder $q) => $q->where('created_at', '>=', $from?->startOfDay()->utc()))
            ->when($to !== null, fn (Builder $q) => $q->where('created_at', '<=', $to?->endOfDay()->utc()))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner
                ->where('order_id', 'like', '%'.$search.'%')
                ->orWhereHas('student', fn (Builder $s) => $s->where('name', 'like', '%'.$search.'%'))));

        $counts = [];
        foreach (self::TABS as $key) {
            $counts[$key] = $this->applyTab(clone $base, $key)->count();
        }

        $orders = $this->applyTab(clone $base, $tab)
            ->with(['student:id,name,nis', 'items:id,payment_order_id,description'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $selectedId = $request->string('order')->toString();
        $selected = $selectedId !== ''
            ? PaymentOrder::query()->with(['student:id,name,nis', 'items', 'reviewer:id,name', 'payment:id,payment_order_id,receipt_number'])->where('order_id', $selectedId)->first()
            : null;

        return Inertia::render('admin/billing/online-payments', [
            'orders' => [
                'data' => $orders->getCollection()->map(fn (PaymentOrder $o): array => $this->row($o))->values()->all(),
                'meta' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'from' => $orders->firstItem(),
                    'to' => $orders->lastItem(),
                ],
            ],
            'counts' => $counts,
            'filters' => [
                'tab' => $tab,
                'q' => $search,
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
            ],
            'selected' => $selected !== null ? $this->detail($selected) : null,
        ]);
    }

    public function check(Request $request, PaymentOrder $paymentOrder): RedirectResponse
    {
        try {
            $row = $this->reconciliation->reconcileOrder($paymentOrder, $this->actor($request));
        } catch (MidtransException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Midtrans tidak dapat dihubungi. Coba lagi beberapa saat lagi.']);

            return back();
        }

        $after = PaymentOrderStatus::from($row['after']);
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $row['before'] === $row['after']
                ? "Status {$paymentOrder->order_id} tetap {$after->label()}."
                : "Status {$paymentOrder->order_id} diperbarui menjadi {$after->label()}.",
        ]);

        return back();
    }

    public function review(Request $request, PaymentOrder $paymentOrder): RedirectResponse
    {
        $this->reconciliation->markReviewed($paymentOrder, $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Order {$paymentOrder->order_id} ditandai sudah ditinjau."]);

        return back();
    }

    public function reconcile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);

        $date = isset($validated['date']) && is_string($validated['date'])
            ? CarbonImmutable::createFromFormat('!Y-m-d', $validated['date'], 'Asia/Makassar')
            : CarbonImmutable::now('Asia/Makassar')->startOfDay();

        $summary = $this->reconciliation->reconcileDate($date ?: CarbonImmutable::now('Asia/Makassar')->startOfDay());

        Inertia::flash('toast', [
            'type' => $summary['discrepancies'] === [] && $summary['failed'] === [] ? 'success' : 'error',
            'message' => sprintf(
                '%d order dicocokkan: %d selisih, %d gagal dicek.',
                $summary['checked'],
                count($summary['discrepancies']),
                count($summary['failed']),
            ),
        ]);

        return back();
    }

    /**
     * @param  Builder<PaymentOrder>  $query
     * @return Builder<PaymentOrder>
     */
    private function applyTab(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            'review' => $query->whereNotNull('review_reason')->whereNull('reviewed_at'),
            'pending' => $query->where('status', PaymentOrderStatus::Pending->value),
            'paid' => $query->where('status', PaymentOrderStatus::Paid->value),
            default => $query->whereIn('status', [
                PaymentOrderStatus::Expired->value,
                PaymentOrderStatus::Failed->value,
                PaymentOrderStatus::Cancelled->value,
            ]),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function row(PaymentOrder $order): array
    {
        return [
            'order_id' => $order->order_id,
            'purpose_label' => $order->purpose->label(),
            'student_name' => $order->student->name,
            'student_nis' => $order->student->nis,
            'summary' => $order->items->sortBy('id')->map(static fn (PaymentOrderItem $i): string => $i->description)->implode(', '),
            'gross_amount' => $order->gross_amount,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'review_reason' => $order->reviewed_at === null ? $order->review_reason?->value : null,
            'review_label' => $order->reviewed_at === null ? $order->review_reason?->label() : null,
            'created_at' => $order->created_at->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(PaymentOrder $order): array
    {
        $payload = $order->last_status_payload ?? [];

        $notifications = PaymentNotification::query()
            ->where('order_id', $order->order_id)
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'transaction_status', 'signature_valid', 'process_result', 'received_at']);

        return [
            ...$this->row($order),
            'review_reason_raw' => $order->review_reason?->value,
            'review_reason_label' => $order->review_reason?->label(),
            'reviewed_at' => $order->reviewed_at?->toIso8601String(),
            'reviewer_name' => $order->reviewer?->name,
            'payment_type' => $order->payment_type,
            'paid_at' => $order->paid_at?->toIso8601String(),
            'expires_at' => $order->expires_at->toIso8601String(),
            'receipt_number' => $order->payment?->receipt_number,
            'items' => $order->items->sortBy('id')->map(static fn (PaymentOrderItem $i): array => [
                'id' => $i->id,
                'description' => $i->description,
                'amount' => $i->amount,
            ])->values()->all(),
            'midtrans' => array_intersect_key($payload, array_flip(self::SAFE_PAYLOAD_KEYS)),
            'notifications' => $notifications->map(static fn (PaymentNotification $n): array => [
                'id' => $n->id,
                'transaction_status' => $n->transaction_status,
                'signature_valid' => $n->signature_valid,
                'process_result' => $n->process_result,
                'received_at' => $n->received_at->toIso8601String(),
            ])->values()->all(),
        ];
    }

    private function parseDate(string $value): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'Asia/Makassar');

        return $date instanceof CarbonImmutable ? $date : null;
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
