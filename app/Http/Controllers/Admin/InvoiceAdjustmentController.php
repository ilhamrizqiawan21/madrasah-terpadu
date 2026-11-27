<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceAdjustmentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Billing\AdjustInvoiceRequest;
use App\Http\Requests\Admin\Billing\CancelInvoiceRequest;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Billing\InvoiceAdjustmentService;
use Illuminate\Http\RedirectResponse;

class InvoiceAdjustmentController extends Controller
{
    public function __construct(
        private readonly InvoiceAdjustmentService $service,
    ) {}

    public function store(AdjustInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();

        $this->service->adjust(
            $actor,
            $invoice,
            InvoiceAdjustmentType::from((string) $validated['type']),
            (int) $validated['amount'],
            (string) $validated['reason'],
        );

        return back()->with('success', 'Penyesuaian tagihan berhasil disimpan.');
    }

    public function cancel(CancelInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();

        $this->service->cancel($actor, $invoice, (string) $validated['reason']);

        return back()->with('success', 'Tagihan berhasil dibatalkan.');
    }
}
