<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\TransferProofStatus;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\TransferProof;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Pengajuan bukti transfer oleh wali dan verifikasi/penolakan oleh bendahara (T-616).
 */
final class TransferProofService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ManualPaymentService $payments,
    ) {}

    public function submit(
        Guardian $guardian,
        Student $student,
        Invoice $invoice,
        UploadedFile $photo,
        int $amount,
        ?string $notes = null,
    ): TransferProof {
        if ($invoice->student_id !== $student->id) {
            abort(404);
        }

        if (! in_array($invoice->status, [InvoiceStatus::Unpaid, InvoiceStatus::Partial], true)) {
            throw ValidationException::withMessages([
                'invoice' => 'Tagihan ini sudah lunas atau dibatalkan.',
            ]);
        }

        $remaining = $invoice->remaining();
        if ($amount <= 0 || $amount > $remaining) {
            throw ValidationException::withMessages([
                'amount' => 'Nominal transfer harus antara Rp 1 dan sisa tagihan.',
            ]);
        }

        $ext = strtolower($photo->getClientOriginalExtension() ?: 'jpg');
        $path = $photo->storeAs(
            "transfer-proofs/{$student->id}",
            Str::uuid()->toString().".{$ext}",
            'local',
        );

        if ($path === false) {
            throw ValidationException::withMessages([
                'photo' => 'Gagal menyimpan foto bukti transfer.',
            ]);
        }

        $cleanNotes = $notes !== null && trim($notes) !== '' ? trim($notes) : null;

        return DB::transaction(function () use ($guardian, $student, $invoice, $amount, $path, $cleanNotes): TransferProof {
            $proof = TransferProof::query()->create([
                'invoice_id' => $invoice->id,
                'student_id' => $student->id,
                'guardian_id' => $guardian->id,
                'amount' => $amount,
                'proof_path' => $path,
                'notes' => $cleanNotes,
                'status' => TransferProofStatus::Pending,
            ]);

            $this->audit->record(
                action: 'transfer_proof.submitted',
                subject: $proof,
                after: [
                    'invoice_id' => $invoice->id,
                    'student_id' => $student->id,
                    'amount' => $amount,
                ],
                userId: $guardian->user_id,
            );

            return $proof;
        });
    }

    public function approve(User $actor, TransferProof $proof): TransferProof
    {
        return DB::transaction(function () use ($actor, $proof): TransferProof {
            $locked = TransferProof::query()
                ->with('student')
                ->whereKey($proof->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== TransferProofStatus::Pending) {
                throw ValidationException::withMessages([
                    'proof' => 'Bukti transfer ini sudah diproses sebelumnya.',
                ]);
            }

            $payment = $this->payments->receive(
                actor: $actor,
                student: $locked->student,
                method: PaymentMethod::Transfer,
                allocations: [
                    ['invoice_id' => $locked->invoice_id, 'amount' => $locked->amount],
                ],
                reference: "Bukti transfer #{$locked->id}",
            );

            $locked->update([
                'status' => TransferProofStatus::Approved,
                'payment_id' => $payment->id,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ]);

            $this->audit->record(
                action: 'transfer_proof.approved',
                subject: $locked,
                before: ['status' => TransferProofStatus::Pending->value],
                after: [
                    'status' => TransferProofStatus::Approved->value,
                    'payment_id' => $payment->id,
                    'receipt_number' => $payment->receipt_number,
                ],
                userId: $actor->id,
            );

            return $locked;
        });
    }

    public function reject(User $actor, TransferProof $proof, string $reason): TransferProof
    {
        $cleanReason = trim($reason);
        if ($cleanReason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan penolakan wajib diisi.',
            ]);
        }

        return DB::transaction(function () use ($actor, $proof, $cleanReason): TransferProof {
            $locked = TransferProof::query()->whereKey($proof->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== TransferProofStatus::Pending) {
                throw ValidationException::withMessages([
                    'proof' => 'Bukti transfer ini sudah diproses sebelumnya.',
                ]);
            }

            $locked->update([
                'status' => TransferProofStatus::Rejected,
                'rejection_reason' => $cleanReason,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ]);

            $this->audit->record(
                action: 'transfer_proof.rejected',
                subject: $locked,
                before: ['status' => TransferProofStatus::Pending->value],
                after: [
                    'status' => TransferProofStatus::Rejected->value,
                    'rejection_reason' => $cleanReason,
                ],
                userId: $actor->id,
            );

            return $locked;
        });
    }
}
