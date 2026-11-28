<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Billing\InvoiceStatusResolver;
use App\Domain\Exam\ExamEligibilityResolver;
use App\Enums\ExamPeriodStatus;
use App\Enums\Feature;
use App\Enums\InvoiceStatus;
use App\Enums\StudentStatus;
use App\Models\ExamEligibilityOverride;
use App\Models\ExamPeriod;
use App\Models\Invoice;
use App\Models\Student;
use App\Notifications\ExamEligibilityReminderNotification;
use App\Services\Features\FeatureFlags;
use App\Services\Notifications\GuardianNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Pengingat H-7 dan H-1 periode ujian ke wali murid bila anak belum layak (T-614).
 * Maksimal satu notifikasi per siswa per hari per periode.
 */
final class SendExamEligibilityReminders implements ShouldQueue
{
    use Queueable;

    public function handle(
        FeatureFlags $features,
        ExamEligibilityResolver $resolver,
        GuardianNotifier $notifier,
    ): int {
        if (! $features->enabled(Feature::ExamEligibility)) {
            return 0;
        }

        $today = CarbonImmutable::now('Asia/Makassar')->startOfDay();
        $targets = [
            'h7' => $today->addDays(7)->toDateString(),
            'h1' => $today->addDay()->toDateString(),
        ];

        $sentCount = 0;

        foreach ($targets as $kind => $startsOn) {
            $periods = ExamPeriod::query()
                ->with('requirements')
                ->where('status', ExamPeriodStatus::Active->value)
                ->whereDate('starts_on', $startsOn)
                ->orderBy('id')
                ->get();

            foreach ($periods as $period) {
                $feeTypeIds = $period->requirements->pluck('fee_type_id')->all();
                if ($feeTypeIds === []) {
                    continue;
                }

                Student::query()
                    ->where('unit_id', $period->unit_id)
                    ->where('status', StudentStatus::Active->value)
                    ->orderBy('id')
                    ->chunkById(200, function (Collection $students) use ($period, $feeTypeIds, $today, $kind, $resolver, $notifier, &$sentCount): void {
                        $studentIds = $students->pluck('id')->all();

                        $invoicesByStudent = Invoice::query()
                            ->whereIn('student_id', $studentIds)
                            ->whereIn('fee_type_id', $feeTypeIds)
                            ->where('status', '!=', InvoiceStatus::Cancelled->value)
                            ->get()
                            ->groupBy('student_id');

                        $dispensedIds = ExamEligibilityOverride::query()
                            ->where('exam_period_id', $period->id)
                            ->whereIn('student_id', $studentIds)
                            ->whereNull('revoked_at')
                            ->pluck('student_id')
                            ->flip();

                        foreach ($students as $student) {
                            if ($dispensedIds->has($student->id)) {
                                continue;
                            }

                            $studentInvoices = $invoicesByStudent->get($student->id, collect());
                            $eval = $resolver->evaluateModels($period->requirements, $studentInvoices);

                            if ($eval['eligible']) {
                                continue;
                            }

                            $blockingAmount = 0;
                            $blockingSet = array_flip($eval['blocking_invoice_ids']);
                            foreach ($studentInvoices as $inv) {
                                if (isset($blockingSet[$inv->id])) {
                                    $blockingAmount += InvoiceStatusResolver::remaining(
                                        $inv->amount,
                                        $inv->adjustment_total,
                                        $inv->paid_total,
                                    );
                                }
                            }

                            $inserted = DB::transaction(static fn (): int => DB::table('exam_eligibility_reminders')->insertOrIgnore([
                                'exam_period_id' => $period->id,
                                'student_id' => $student->id,
                                'sent_date' => $today->toDateString(),
                                'kind' => $kind,
                                'sent_at' => now(),
                            ]));

                            if ($inserted !== 1) {
                                continue;
                            }

                            $notifier->notifyStudentGuardians(
                                $student,
                                new ExamEligibilityReminderNotification(
                                    examPeriodId: $period->id,
                                    studentId: $student->id,
                                    studentName: $student->nickname ?? $student->name,
                                    periodName: $period->name,
                                    blockingCount: count($eval['blocking_invoice_ids']),
                                    blockingAmount: $blockingAmount,
                                    startsOnLabel: $period->starts_on->translatedFormat('d M Y'),
                                ),
                            );

                            $sentCount++;
                        }
                    });
            }
        }

        return $sentCount;
    }
}
