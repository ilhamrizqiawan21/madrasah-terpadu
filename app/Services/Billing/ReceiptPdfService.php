<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Services\Settings\SettingsRepository;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Pembuatan kuitansi PDF pembayaran tagihan (T-607).
 */
final class ReceiptPdfService
{
    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    public function download(Payment $payment): Response
    {
        $payment->load([
            'student.unit:id,code,name',
            'receiver:id,name',
            'allocations.invoice.feeType:id,name',
        ]);

        $identity = $this->settings->identity();

        $cashierLabel = $payment->method === PaymentMethod::Midtrans
            ? 'Pembayaran online'
            : ($payment->receiver->name ?? 'Kasir sekolah');

        $rows = $payment->allocations->map(static fn ($alloc): array => [
            'description' => $alloc->invoice->description,
            'fee_type' => $alloc->invoice->feeType->name,
            'amount' => Money::format($alloc->amount),
        ])->values()->all();

        $pdf = Pdf::loadView('pdf.payment-receipt', [
            'foundationName' => $identity['foundation_name'] ?: $identity['app_name'],
            'appName' => $identity['app_name'],
            'address' => $identity['address'],
            'phone' => $identity['phone'],
            'receiptNumber' => $payment->receipt_number,
            'paidAt' => $payment->paid_at->timezone('Asia/Makassar')->format('d/m/Y H.i').' WITA',
            'studentName' => $payment->student->name,
            'studentNis' => $payment->student->nis,
            'unitName' => $payment->student->unit->name,
            'methodLabel' => $payment->method->label(),
            'reference' => $payment->reference,
            'cashierLabel' => $cashierLabel,
            'rows' => $rows,
            'totalFormatted' => Money::format($payment->amount),
            'isReversed' => $payment->reversed_at !== null,
            'reversalReason' => $payment->reversal_reason,
        ])->setPaper('a5', 'landscape');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="kuitansi-'.$payment->receipt_number.'.pdf"',
        ]);
    }
}
