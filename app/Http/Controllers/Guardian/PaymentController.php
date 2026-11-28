<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guardian;

use App\Enums\PaymentOrderPurpose;
use App\Enums\PaymentOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guardian\StorePaymentOrderRequest;
use App\Http\Requests\Guardian\StoreWalletTopupOrderRequest;
use App\Models\Guardian;
use App\Models\PaymentOrder;
use App\Models\PaymentOrderItem;
use App\Models\Student;
use App\Models\User;
use App\Services\Payments\MidtransClient;
use App\Services\Payments\PaymentOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pembayaran online wali lewat Midtrans Snap (T-702, PAYMENTS §3).
 * Callback Snap di browser hanya bernavigasi ke halaman status; status berubah dari webhook.
 */
class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentOrderService $orders,
        private readonly MidtransClient $midtrans,
    ) {}

    public function store(StorePaymentOrderRequest $request, Student $student): RedirectResponse
    {
        $order = $this->orders->createForInvoices(
            $this->guardianOf($request),
            $student,
            $request->invoiceIds(),
        );

        return to_route('guardian.payments.show', $order);
    }

    /**
     * Isi saldo kantin online (T-805). Saldo bertambah hanya setelah webhook settlement.
     */
    public function topUp(StoreWalletTopupOrderRequest $request, Student $student): RedirectResponse
    {
        $order = $this->orders->createForTopUp(
            $this->guardianOf($request),
            $student,
            $request->integer('amount'),
        );

        return to_route('guardian.payments.show', $order);
    }

    public function show(Request $request, PaymentOrder $paymentOrder): Response|RedirectResponse
    {
        $this->assertOwned($request, $paymentOrder);

        if (! $paymentOrder->isAwaitingPayment() || $paymentOrder->snap_token === null) {
            return to_route('guardian.payments.finish', $paymentOrder);
        }

        return $this->render($paymentOrder, 'confirm');
    }

    public function finish(Request $request, PaymentOrder $paymentOrder): Response
    {
        $this->assertOwned($request, $paymentOrder);

        return $this->render($paymentOrder, 'status');
    }

    private function render(PaymentOrder $order, string $view): Response
    {
        $order->load(['student:id,name', 'items', 'payment:id,payment_order_id']);

        return Inertia::render('guardian/payment', [
            'view' => $view,
            'order' => [
                'order_id' => $order->order_id,
                'purpose' => $order->purpose->value,
                'purpose_label' => $order->purpose->label(),
                'student_id' => $order->student_id,
                'student_name' => $order->student->name,
                'gross_amount' => $order->gross_amount,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'awaiting_payment' => $order->isAwaitingPayment(),
                'expires_at' => $order->expires_at->toIso8601String(),
                'paid_at' => $order->paid_at?->toIso8601String(),
                'payment_id' => $order->purpose === PaymentOrderPurpose::Invoices && $order->status === PaymentOrderStatus::Paid
                    ? $order->payment?->id
                    : null,
                'items' => $order->items
                    ->sortBy('id')
                    ->map(static fn (PaymentOrderItem $item): array => [
                        'id' => $item->id,
                        'description' => $item->description,
                        'amount' => $item->amount,
                    ])
                    ->values()
                    ->all(),
            ],
            // Snap token & client key hanya dikirim selama order masih bisa dibayar.
            'snap' => $view === 'confirm' ? [
                'token' => $order->snap_token,
                'redirect_url' => $order->snap_redirect_url,
                'client_key' => (string) config('services.midtrans.client_key'),
                'script_url' => $this->midtrans->snapJsUrl(),
            ] : null,
        ]);
    }

    private function guardianOf(Request $request): Guardian
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->guardian instanceof Guardian, 404);

        return $user->guardian;
    }

    /**
     * Order milik anak wali lain dijawab 404 agar keberadaannya tidak bocor (AI_RULES §5).
     */
    private function assertOwned(Request $request, PaymentOrder $order): void
    {
        $guardian = $this->guardianOf($request);

        abort_unless($guardian->students()->whereKey($order->student_id)->exists(), 404);
    }
}
