<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guardian\StoreTransferProofRequest;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\TransferProof;
use App\Models\User;
use App\Services\Billing\TransferProofService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransferProofController extends Controller
{
    public function __construct(
        private readonly TransferProofService $service,
    ) {}

    public function store(StoreTransferProofRequest $request, Student $student, Invoice $invoice): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->guardian !== null, 403);

        $validated = $request->validated();
        /** @var UploadedFile $photo */
        $photo = $request->file('photo');

        $this->service->submit(
            guardian: $user->guardian,
            student: $student,
            invoice: $invoice,
            photo: $photo,
            amount: (int) $validated['amount'],
            notes: isset($validated['notes']) ? (string) $validated['notes'] : null,
        );

        return back()->with('success', 'Bukti transfer berhasil diunggah dan menunggu verifikasi bendahara.');
    }

    public function file(Student $student, TransferProof $transferProof): StreamedResponse
    {
        if ($transferProof->student_id !== $student->id) {
            abort(404);
        }

        abort_unless(Storage::disk('local')->exists($transferProof->proof_path), 404);

        return Storage::disk('local')->response($transferProof->proof_path);
    }
}
