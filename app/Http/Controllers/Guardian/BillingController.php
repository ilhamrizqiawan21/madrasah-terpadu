<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guardian;

use App\Enums\Feature;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentOrderItem;
use App\Models\Student;
use App\Models\User;
use App\Services\Features\FeatureFlags;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function __construct(
        private readonly FeatureFlags $features,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $guardian = $user->guardian;
        $midtransEnabled = $this->features->enabled(Feature::Midtrans);

        if ($guardian === null) {
            return Inertia::render('guardian/billing', [
                'children' => [],
                'familyTotalRemaining' => 0,
                'midtransEnabled' => $midtransEnabled,
            ]);
        }

        $children = $guardian->students()
            ->with('unit:id,code,name')
            ->orderBy('name')
            ->get();

        $childIds = $children->pluck('id')->all();
        $todayWita = now()->timezone('Asia/Makassar')->toDateString();

        $invoicesByStudent = $childIds === []
            ? collect()
            : Invoice::query()
                ->with('feeType:id,name')
                ->whereIn('student_id', $childIds)
                ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value])
                ->orderBy('due_date')
                ->orderBy('id')
                ->get()
                ->groupBy('student_id');

        $paymentsByStudent = $childIds === []
            ? collect()
            : Payment::query()
                ->whereIn('student_id', $childIds)
                ->whereNull('reversed_at')
                ->orderByDesc('paid_at')
                ->orderByDesc('id')
                ->limit(100)
                ->get()
                ->groupBy('student_id');

        // Tagihan yang sedang menunggu pembayaran online tidak bisa dipilih lagi (T-702).
        $pendingOrderByInvoice = $invoicesByStudent->isEmpty()
            ? collect()
            : PaymentOrderItem::query()
                ->with('order:id,order_id')
                ->whereIn('invoice_id', $invoicesByStudent->flatten()->pluck('id')->all())
                ->whereHas('order', static fn ($query) => $query
                    ->where('status', PaymentOrderStatus::Pending->value)
                    ->where('expires_at', '>', now()))
                ->orderBy('id')
                ->get()
                ->mapWithKeys(static fn (PaymentOrderItem $item): array => [$item->invoice_id => $item->order->order_id]);

        $familyTotalRemaining = 0;

        $childPayloads = $children->map(function (Student $child) use (
            $invoicesByStudent,
            $paymentsByStudent,
            $pendingOrderByInvoice,
            $todayWita,
            &$familyTotalRemaining,
        ): array {
            $openInvoices = $invoicesByStudent->get($child->id, collect())
                ->map(static function (Invoice $invoice) use ($todayWita, $pendingOrderByInvoice): array {
                    $isOverdue = $invoice->due_date->toDateString() < $todayWita;

                    return [
                        'id' => $invoice->id,
                        'fee_type_name' => $invoice->feeType->name,
                        'description' => $invoice->description,
                        'due_date' => $invoice->due_date->toDateString(),
                        'amount' => $invoice->amount,
                        'adjustment_total' => $invoice->adjustment_total,
                        'paid_total' => $invoice->paid_total,
                        'remaining' => $invoice->remaining(),
                        'status' => $invoice->status->value,
                        'status_label' => $invoice->status->label(),
                        'is_overdue' => $isOverdue,
                        'pending_order_id' => $pendingOrderByInvoice->get($invoice->id),
                    ];
                })
                ->values()
                ->all();

            $childRemaining = array_sum(array_column($openInvoices, 'remaining'));
            $familyTotalRemaining += $childRemaining;

            $payments = $paymentsByStudent->get($child->id, collect())
                ->take(20)
                ->map(static fn (Payment $payment): array => [
                    'id' => $payment->id,
                    'receipt_number' => $payment->receipt_number,
                    'method_label' => $payment->method->label(),
                    'amount' => $payment->amount,
                    'paid_at' => $payment->paid_at->toIso8601String(),
                ])
                ->values()
                ->all();

            return [
                'id' => $child->id,
                'name' => $child->name,
                'nis' => $child->nis,
                'unit_name' => $child->unit->name,
                'total_remaining' => $childRemaining,
                'open_invoices' => $openInvoices,
                'payments' => $payments,
            ];
        })->values()->all();

        return Inertia::render('guardian/billing', [
            'children' => $childPayloads,
            'familyTotalRemaining' => $familyTotalRemaining,
            'midtransEnabled' => $midtransEnabled,
        ]);
    }
}
