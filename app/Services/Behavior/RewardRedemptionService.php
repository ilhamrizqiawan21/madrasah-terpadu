<?php

declare(strict_types=1);

namespace App\Services\Behavior;

use App\Enums\PointAccount;
use App\Models\AcademicYear;
use App\Models\CanteenVoucher;
use App\Models\RewardOffer;
use App\Models\RewardRedemption;
use App\Models\Student;
use App\Models\User;
use App\Notifications\RewardRedemptionDecidedNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\GuardianNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RewardRedemptionService
{
    public function __construct(
        private readonly PointLedgerService $ledger,
        private readonly AuditLogger $audit,
        private readonly GuardianNotifier $notifier,
    ) {}

    public function request(
        User $actor,
        Student $student,
        RewardOffer $offer,
        ?AcademicYear $academicYear = null,
    ): RewardRedemption {
        $year = $academicYear ?? AcademicYear::query()->where('is_active', true)->first();
        if ($year === null) {
            throw ValidationException::withMessages([
                'reward_offer_id' => 'Belum ada tahun ajaran aktif.',
            ]);
        }

        if (! $offer->is_active) {
            throw ValidationException::withMessages([
                'reward_offer_id' => 'Penawaran reward sedang tidak aktif.',
            ]);
        }

        if ($offer->unit_id !== null && $offer->unit_id !== $student->unit_id) {
            throw ValidationException::withMessages([
                'reward_offer_id' => 'Penawaran reward tidak berlaku untuk unit siswa ini.',
            ]);
        }

        if ($offer->stock !== null && $offer->stock <= 0) {
            throw ValidationException::withMessages([
                'reward_offer_id' => 'Stok penawaran reward sudah habis.',
            ]);
        }

        $balance = $this->ledger->balanceFor($student->id, $year->id, PointAccount::Merit);
        if ($balance < $offer->cost_points) {
            throw ValidationException::withMessages([
                'reward_offer_id' => 'Poin kebaikan siswa tidak mencukupi untuk penawaran ini.',
            ]);
        }

        return DB::transaction(function () use ($actor, $student, $offer, $year): RewardRedemption {
            $redemption = RewardRedemption::query()->create([
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'reward_offer_id' => $offer->id,
                'cost_points' => $offer->cost_points,
                'voucher_amount' => $offer->voucher_amount,
                'requested_by' => $actor->id,
                'status' => 'pending',
            ]);

            $this->audit->record(
                action: 'reward.requested',
                subject: $redemption,
                after: [
                    'student_id' => $student->id,
                    'reward_offer_id' => $offer->id,
                    'cost_points' => $offer->cost_points,
                ],
                userId: $actor->id,
            );

            return $redemption;
        });
    }

    public function approve(User $actor, RewardRedemption $redemption, ?string $note = null): RewardRedemption
    {
        return DB::transaction(function () use ($actor, $redemption, $note): RewardRedemption {
            /** @var RewardRedemption $locked */
            $locked = RewardRedemption::query()
                ->with(['student', 'academicYear'])
                ->lockForUpdate()
                ->findOrFail($redemption->id);

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'decision_note' => 'Pengajuan penukaran ini sudah diproses.',
                ]);
            }

            /** @var RewardOffer $offer */
            $offer = RewardOffer::query()->lockForUpdate()->findOrFail($locked->reward_offer_id);

            if ($offer->stock !== null) {
                if ($offer->stock <= 0) {
                    throw ValidationException::withMessages([
                        'decision_note' => 'Stok penawaran reward sudah habis.',
                    ]);
                }

                $offer->decrement('stock');
            }

            $balance = $this->ledger->balanceFor(
                $locked->student_id,
                $locked->academic_year_id,
                PointAccount::Merit,
            );

            if ($balance < $locked->cost_points) {
                throw ValidationException::withMessages([
                    'decision_note' => 'Poin kebaikan siswa tidak mencukupi.',
                ]);
            }

            $this->ledger->post(
                student: $locked->student,
                academicYear: $locked->academicYear,
                account: PointAccount::Merit,
                delta: -$locked->cost_points,
                source: $locked,
                idempotencyKey: "reward-redemption:{$locked->id}",
            );

            $trimmedNote = $note !== null && trim($note) !== '' ? trim($note) : null;
            $locked->update([
                'status' => 'approved',
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decision_note' => $trimmedNote,
            ]);

            $timezone = config('sekolah.timezone', 'Asia/Makassar');
            $expiresOn = now()->timezone(is_string($timezone) ? $timezone : 'Asia/Makassar')
                ->addDays($offer->voucher_valid_days)
                ->toDateString();

            CanteenVoucher::query()->create([
                'student_id' => $locked->student_id,
                'reward_redemption_id' => $locked->id,
                'amount' => $locked->voucher_amount,
                'remaining' => $locked->voucher_amount,
                'expires_on' => $expiresOn,
                'created_at' => now(),
            ]);

            $this->audit->record(
                action: 'reward.approved',
                subject: $locked,
                before: ['status' => 'pending'],
                after: ['status' => 'approved', 'voucher_amount' => $locked->voucher_amount],
                userId: $actor->id,
            );

            $this->notifier->notifyStudentGuardians(
                $locked->student,
                new RewardRedemptionDecidedNotification(
                    redemptionId: $locked->id,
                    studentId: $locked->student_id,
                    studentName: $locked->student->name,
                    offerName: $offer->name,
                    voucherAmount: $locked->voucher_amount,
                    approved: true,
                    note: $trimmedNote,
                ),
            );

            return $locked;
        });
    }

    public function reject(User $actor, RewardRedemption $redemption, string $note): RewardRedemption
    {
        $trimmedNote = trim($note);
        if ($trimmedNote === '') {
            throw ValidationException::withMessages([
                'decision_note' => 'Alasan penolakan wajib diisi.',
            ]);
        }

        return DB::transaction(function () use ($actor, $redemption, $trimmedNote): RewardRedemption {
            /** @var RewardRedemption $locked */
            $locked = RewardRedemption::query()
                ->with(['student', 'offer'])
                ->lockForUpdate()
                ->findOrFail($redemption->id);

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'decision_note' => 'Pengajuan penukaran ini sudah diproses.',
                ]);
            }

            $locked->update([
                'status' => 'rejected',
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decision_note' => $trimmedNote,
            ]);

            $this->audit->record(
                action: 'reward.rejected',
                subject: $locked,
                before: ['status' => 'pending'],
                after: ['status' => 'rejected', 'decision_note' => $trimmedNote],
                userId: $actor->id,
            );

            $this->notifier->notifyStudentGuardians(
                $locked->student,
                new RewardRedemptionDecidedNotification(
                    redemptionId: $locked->id,
                    studentId: $locked->student_id,
                    studentName: $locked->student->name,
                    offerName: $locked->offer->name,
                    voucherAmount: $locked->voucher_amount,
                    approved: false,
                    note: $trimmedNote,
                ),
            );

            return $locked;
        });
    }
}
