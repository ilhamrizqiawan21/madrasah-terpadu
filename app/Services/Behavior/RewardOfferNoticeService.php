<?php

declare(strict_types=1);

namespace App\Services\Behavior;

use App\Models\AcademicYear;
use App\Models\RewardOffer;
use App\Models\RewardOfferNotice;
use App\Models\Student;
use App\Notifications\RewardOfferAvailableNotification;
use App\Services\Notifications\GuardianNotifier;
use Illuminate\Support\Facades\DB;

class RewardOfferNoticeService
{
    public function __construct(
        private readonly GuardianNotifier $notifier,
    ) {}

    public function notifyEligibleOffers(Student $student, AcademicYear $academicYear, int $meritBalance): void
    {
        if ($meritBalance <= 0) {
            return;
        }

        DB::transaction(function () use ($student, $academicYear, $meritBalance): void {
            /** @var list<int> $alreadyNotifiedIds */
            $alreadyNotifiedIds = RewardOfferNotice::query()
                ->where('student_id', $student->id)
                ->where('academic_year_id', $academicYear->id)
                ->pluck('reward_offer_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->values()
                ->all();

            $eligibleOffers = RewardOffer::query()
                ->active()
                ->where('cost_points', '<=', $meritBalance)
                ->where(function ($q) use ($student): void {
                    $q->where('unit_id', $student->unit_id)->orWhereNull('unit_id');
                })
                ->where(function ($q): void {
                    $q->whereNull('stock')->orWhere('stock', '>', 0);
                })
                ->when($alreadyNotifiedIds !== [], fn ($q) => $q->whereNotIn('id', $alreadyNotifiedIds))
                ->orderBy('cost_points')
                ->get();

            foreach ($eligibleOffers as $offer) {
                RewardOfferNotice::query()->create([
                    'student_id' => $student->id,
                    'reward_offer_id' => $offer->id,
                    'academic_year_id' => $academicYear->id,
                    'notified_at' => now(),
                ]);

                $this->notifier->notifyStudentGuardians(
                    $student,
                    new RewardOfferAvailableNotification(
                        studentId: $student->id,
                        studentName: $student->name,
                        offerId: $offer->id,
                        offerName: $offer->name,
                        costPoints: $offer->cost_points,
                    ),
                );
            }
        });
    }
}
