<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Domain\Billing\InvoiceStatusResolver;
use App\Domain\Exam\ExamEligibilityResolver;
use App\Enums\InvoiceStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\ExamEligibilityOverride;
use App\Models\ExamPeriod;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Menyusun daftar kelayakan siswa per rombel untuk periode ujian (T-613, ADR-016).
 */
final class ExamEligibilityListService
{
    public function __construct(
        private readonly ExamEligibilityResolver $resolver,
    ) {}

    /**
     * @return Collection<int, Classroom>
     */
    public function accessibleClassrooms(ExamPeriod $period, User $viewer, ?int $academicYearId): Collection
    {
        if ($academicYearId === null) {
            return collect();
        }

        $canViewAll = $viewer->can(Permission::InvoicesView->value);
        $isTeacher = $viewer->hasRole(Role::Teacher->value);

        if (! $canViewAll && ! $isTeacher) {
            return collect();
        }

        return Classroom::query()
            ->where('unit_id', $period->unit_id)
            ->where('academic_year_id', $academicYearId)
            ->where('is_active', true)
            ->when(! $canViewAll, fn (Builder $q) => $q->where('homeroom_teacher_id', $viewer->id))
            ->orderBy('grade')
            ->orderBy('name')
            ->get(['id', 'name', 'grade']);
    }

    /**
     * @param  Collection<int, Classroom>  $classrooms
     * @return array{
     *     summary: array{total: int, eligible: int, ineligible: int, dispensed: int},
     *     rows: list<array{
     *         student_id: int,
     *         nis: string,
     *         name: string,
     *         classroom_id: int,
     *         classroom_name: string,
     *         status: 'eligible'|'ineligible'|'dispensed',
     *         status_label: string,
     *         blocking_count: int,
     *         blocking_amount: int,
     *         dispensation_id: ?int,
     *         dispensation_reason: ?string,
     *     }>,
     * }
     */
    public function build(
        ExamPeriod $period,
        Collection $classrooms,
        ?int $classroomId,
        ?string $statusFilter,
        ?string $search,
        ?int $academicYearId,
    ): array {
        $period->loadMissing('requirements');

        if ($classrooms->isEmpty() || $academicYearId === null) {
            return [
                'summary' => ['total' => 0, 'eligible' => 0, 'ineligible' => 0, 'dispensed' => 0],
                'rows' => [],
            ];
        }

        $targetClassroomIds = $classroomId !== null
            ? [$classroomId]
            : $classrooms->pluck('id')->all();

        $enrollments = Enrollment::query()
            ->where('academic_year_id', $academicYearId)
            ->whereIn('classroom_id', $targetClassroomIds)
            ->whereHas('student', function (Builder $q) use ($search): void {
                $q->where('status', StudentStatus::Active->value);
                if ($search !== null && trim($search) !== '') {
                    $term = '%'.trim($search).'%';
                    $q->where(fn (Builder $sq) => $sq->where('name', 'like', $term)->orWhere('nis', 'like', $term));
                }
            })
            ->with(['student:id,nis,name', 'classroom:id,name'])
            ->get(['id', 'student_id', 'classroom_id']);

        $studentIds = $enrollments->pluck('student_id')->unique()->values()->all();
        $feeTypeIds = $period->requirements->pluck('fee_type_id')->all();

        /** @var Collection<int, Collection<int, Invoice>> $invoicesByStudent */
        $invoicesByStudent = ($studentIds !== [] && $feeTypeIds !== [])
            ? Invoice::query()
                ->whereIn('student_id', $studentIds)
                ->whereIn('fee_type_id', $feeTypeIds)
                ->where('status', '!=', InvoiceStatus::Cancelled->value)
                ->get()
                ->groupBy('student_id')
            : collect();

        /** @var Collection<int, ExamEligibilityOverride> $overridesByStudent */
        $overridesByStudent = $studentIds !== []
            ? ExamEligibilityOverride::query()
                ->where('exam_period_id', $period->id)
                ->whereIn('student_id', $studentIds)
                ->whereNull('revoked_at')
                ->get()
                ->keyBy('student_id')
            : collect();

        $summary = ['total' => 0, 'eligible' => 0, 'ineligible' => 0, 'dispensed' => 0];
        $rows = [];

        foreach ($enrollments as $enrollment) {
            $studentInvoices = $invoicesByStudent->get($enrollment->student_id, collect());
            /** @var ExamEligibilityOverride|null $override */
            $override = $overridesByStudent->get($enrollment->student_id);

            $eval = $this->resolver->evaluateModels(
                $period->requirements,
                $studentInvoices,
                $override !== null,
            );

            $summary['total']++;
            $summary[$eval['status']]++;

            if ($statusFilter !== null && $statusFilter !== '' && $eval['status'] !== $statusFilter) {
                continue;
            }

            $blockingAmount = 0;
            if ($eval['blocking_invoice_ids'] !== []) {
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
            }

            $statusLabel = match ($eval['status']) {
                'eligible' => 'Layak',
                'dispensed' => 'Dispensasi',
                'ineligible' => 'Belum layak',
            };

            $rows[] = [
                'student_id' => $enrollment->student->id,
                'nis' => $enrollment->student->nis,
                'name' => $enrollment->student->name,
                'classroom_id' => $enrollment->classroom->id,
                'classroom_name' => $enrollment->classroom->name,
                'status' => $eval['status'],
                'status_label' => $statusLabel,
                'blocking_count' => count($eval['blocking_invoice_ids']),
                'blocking_amount' => $blockingAmount,
                'dispensation_id' => $override?->id,
                'dispensation_reason' => $override?->reason,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => [$a['classroom_name'], $a['name']] <=> [$b['classroom_name'], $b['name']]);

        return [
            'summary' => $summary,
            'rows' => $rows,
        ];
    }
}
