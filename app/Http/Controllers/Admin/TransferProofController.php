<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\TransferProofStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Billing\RejectTransferProofRequest;
use App\Models\TransferProof;
use App\Models\User;
use App\Services\Billing\TransferProofService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransferProofController extends Controller
{
    public function __construct(
        private readonly TransferProofService $service,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', TransferProof::class);

        $status = $request->string('status')->toString();

        $proofs = TransferProof::query()
            ->with([
                'student:id,nis,name,unit_id',
                'student.unit:id,code',
                'invoice:id,description',
                'guardian.user:id,name',
                'reviewer:id,name',
            ])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $items = $proofs->getCollection()
            ->map(static fn (TransferProof $p): array => [
                'id' => $p->id,
                'student_name' => $p->student->name,
                'student_nis' => $p->student->nis,
                'unit_code' => $p->student->unit->code,
                'invoice_description' => $p->invoice->description,
                'guardian_name' => $p->guardian->user->name,
                'amount' => $p->amount,
                'notes' => $p->notes,
                'status' => $p->status->value,
                'status_label' => $p->status->label(),
                'rejection_reason' => $p->rejection_reason,
                'reviewer_name' => $p->reviewer?->name,
                'created_at' => $p->created_at->toIso8601String(),
            ])
            ->values()
            ->all();

        return Inertia::render('admin/billing/transfer-proofs', [
            'proofs' => [
                'data' => $items,
                'meta' => [
                    'current_page' => $proofs->currentPage(),
                    'last_page' => $proofs->lastPage(),
                    'per_page' => $proofs->perPage(),
                    'total' => $proofs->total(),
                    'from' => $proofs->firstItem(),
                    'to' => $proofs->lastItem(),
                ],
            ],
            'statuses' => array_map(
                static fn (TransferProofStatus $s): array => ['value' => $s->value, 'label' => $s->label()],
                TransferProofStatus::cases(),
            ),
            'filters' => [
                'status' => $status,
            ],
        ]);
    }

    public function file(TransferProof $transferProof): StreamedResponse
    {
        Gate::authorize('review', $transferProof);
        abort_unless(Storage::disk('local')->exists($transferProof->proof_path), 404);

        return Storage::disk('local')->response($transferProof->proof_path);
    }

    public function approve(Request $request, TransferProof $transferProof): RedirectResponse
    {
        Gate::authorize('review', $transferProof);

        /** @var User $actor */
        $actor = $request->user();
        $this->service->approve($actor, $transferProof);

        return back()->with('success', 'Bukti transfer disetujui dan pembayaran tercatat.');
    }

    public function reject(RejectTransferProofRequest $request, TransferProof $transferProof): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();

        $this->service->reject($actor, $transferProof, (string) $validated['reason']);

        return back()->with('success', 'Bukti transfer ditolak.');
    }
}
