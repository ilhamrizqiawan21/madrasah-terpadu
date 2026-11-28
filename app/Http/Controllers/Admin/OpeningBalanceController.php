<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Billing\ImportOpeningBalanceRequest;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Billing\OpeningBalanceImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OpeningBalanceController extends Controller
{
    public function __construct(
        private readonly OpeningBalanceImportService $service,
    ) {}

    public function index(): Response
    {
        Gate::authorize('issue', Invoice::class);

        return Inertia::render('admin/billing/opening-balances');
    }

    public function template(): StreamedResponse
    {
        Gate::authorize('issue', Invoice::class);

        return $this->service->downloadTemplate();
    }

    public function preview(ImportOpeningBalanceRequest $request): JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');

        return response()->json($this->service->preview($file));
    }

    public function store(ImportOpeningBalanceRequest $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        /** @var UploadedFile $file */
        $file = $request->file('file');

        $result = $this->service->import($actor, $file);

        return back()
            ->with('success', "Impor saldo awal selesai: {$result['created']} dibuat, {$result['skipped']} dilewati.")
            ->with('importResult', $result);
    }
}
