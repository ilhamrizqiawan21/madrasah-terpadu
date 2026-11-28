<?php

declare(strict_types=1);

namespace App\Domain\Exam;

use App\Domain\Billing\InvoiceStatusResolver;
use App\Enums\ExamPeriodStatus;
use App\Enums\ExamRequirementScope;
use App\Enums\Feature;
use App\Enums\InvoiceStatus;
use App\Models\Exam;
use App\Models\ExamPeriod;
use App\Models\ExamPeriodRequirement;
use App\Models\Invoice;
use App\Models\Student;
use App\Services\Features\FeatureFlags;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Penentu kelayakan siswa mengikuti ujian berdasarkan syarat tagihan periode ujian (ADR-016 / T-611).
 * Selama exam_period_id null atau fitur exam_eligibility nonaktif, semua siswa layak.
 *
 * @phpstan-type RequirementRule array{fee_type_id: int, invoice_scope: ExamRequirementScope, period_until: ?CarbonImmutable}
 * @phpstan-type InvoiceSnapshot array{id: int, fee_type_id: int, amount: int, adjustment_total: int, paid_total: int, status: InvoiceStatus, period: ?CarbonImmutable}
 */
final class ExamEligibilityResolver
{
    /**
     * Evaluasi murni tanpa ketergantungan database/framework.
     *
     * @param  list<RequirementRule>  $requirements
     * @param  list<InvoiceSnapshot>  $invoices
     * @return array{eligible: bool, status: 'eligible'|'ineligible'|'dispensed', blocking_invoice_ids: list<int>}
     */
    public function evaluate(array $requirements, array $invoices, bool $hasActiveDispensation = false): array
    {
        $blockingIds = [];

        foreach ($requirements as $req) {
            foreach ($invoices as $inv) {
                if ($inv['fee_type_id'] !== $req['fee_type_id']) {
                    continue;
                }

                if ($inv['status'] === InvoiceStatus::Cancelled) {
                    continue;
                }

                if ($req['invoice_scope'] === ExamRequirementScope::UpToPeriod && $req['period_until'] !== null) {
                    if ($inv['period'] === null) {
                        continue;
                    }

                    $limit = $req['period_until']->startOfMonth();
                    if ($inv['period']->startOfMonth()->greaterThan($limit)) {
                        continue;
                    }
                }

                $remaining = InvoiceStatusResolver::remaining(
                    $inv['amount'],
                    $inv['adjustment_total'],
                    $inv['paid_total'],
                );

                if ($remaining > 0) {
                    $blockingIds[] = $inv['id'];
                }
            }
        }

        $blockingIds = array_values(array_unique($blockingIds));

        if ($hasActiveDispensation) {
            return [
                'eligible' => true,
                'status' => 'dispensed',
                'blocking_invoice_ids' => $blockingIds,
            ];
        }

        return [
            'eligible' => $blockingIds === [],
            'status' => $blockingIds === [] ? 'eligible' : 'ineligible',
            'blocking_invoice_ids' => $blockingIds,
        ];
    }

    /**
     * @param  Collection<int, ExamPeriodRequirement>  $requirements
     * @param  Collection<int, Invoice>  $invoices
     * @return array{eligible: bool, status: 'eligible'|'ineligible'|'dispensed', blocking_invoice_ids: list<int>}
     */
    public function evaluateModels(Collection $requirements, Collection $invoices, bool $hasActiveDispensation = false): array
    {
        $reqRules = array_values($requirements->map(static fn (ExamPeriodRequirement $req): array => [
            'fee_type_id' => $req->fee_type_id,
            'invoice_scope' => $req->invoice_scope,
            'period_until' => $req->period_until,
        ])->all());

        $invSnapshots = array_values($invoices->map(static fn (Invoice $inv): array => [
            'id' => $inv->id,
            'fee_type_id' => $inv->fee_type_id,
            'amount' => $inv->amount,
            'adjustment_total' => $inv->adjustment_total,
            'paid_total' => $inv->paid_total,
            'status' => $inv->status,
            'period' => $inv->period,
        ])->all());

        return $this->evaluate($reqRules, $invSnapshots, $hasActiveDispensation);
    }

    public function isEligible(Exam $exam, Student $student): bool
    {
        if ($exam->exam_period_id === null) {
            return true;
        }

        if (! app(FeatureFlags::class)->enabled(Feature::ExamEligibility)) {
            return true;
        }

        $period = ExamPeriod::query()->with('requirements')->find($exam->exam_period_id);
        if (! $period instanceof ExamPeriod || $period->status !== ExamPeriodStatus::Active) {
            return true;
        }

        $feeTypeIds = $period->requirements->pluck('fee_type_id')->all();
        if ($feeTypeIds === []) {
            return true;
        }

        $invoices = Invoice::query()
            ->where('student_id', $student->id)
            ->whereIn('fee_type_id', $feeTypeIds)
            ->where('status', '!=', InvoiceStatus::Cancelled->value)
            ->get();

        return $this->evaluateModels($period->requirements, $invoices)['eligible'];
    }
}
