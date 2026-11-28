<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Domain\Billing\BillingPeriod;
use App\Domain\Billing\InvoiceStatusResolver;
use App\Enums\BillingTargetType;
use App\Enums\FeeRecurrence;
use App\Enums\InvoiceAdjustmentType;
use App\Enums\InvoiceStatus;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\BillingRun;
use App\Models\Classroom;
use App\Models\FeeType;
use App\Models\Invoice;
use App\Models\InvoiceAdjustment;
use App\Models\Student;
use App\Models\StudentDiscount;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Pratinjau dan eksekusi penerbitan tagihan massal (T-602).
 */
final class BillingRunService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *   items: list<array{
     *     key: string,
     *     student_id: int,
     *     student_name: string,
     *     nis: string,
     *     classroom_name: string|null,
     *     period: string,
     *     period_label: string,
     *     description: string,
     *     due_date: string,
     *     amount: int,
     *     state: string,
     *     state_label: string
     *   }>,
     *   summary: array{
     *     will_create: int,
     *     already_exists: int,
     *     skipped_inactive: int,
     *     total_amount: int
     *   }
     * }
     */
    public function preview(array $data): array
    {
        $resolved = $this->resolveContext($data);
        /** @var Collection<int, Student> $students */
        $students = $resolved['students'];
        /** @var list<CarbonImmutable> $periods */
        $periods = $resolved['periods'];
        /** @var FeeType $feeType */
        $feeType = $resolved['fee_type'];
        /** @var AcademicYear|null $activeYear */
        $activeYear = $resolved['active_year'];
        $defaultAmount = $resolved['default_amount'];
        $overrides = $resolved['overrides'];
        $dueDay = $resolved['due_day'];
        $fixedDueDate = $resolved['fixed_due_date'];

        $studentIds = $students->pluck('id')->all();
        $periodLookup = array_flip(array_map(static fn (CarbonImmutable $p): string => $p->toDateString(), $periods));

        $existingKeys = [];
        if ($studentIds !== [] && $periodLookup !== []) {
            $existing = Invoice::query()
                ->where('fee_type_id', $feeType->id)
                ->whereIn('student_id', $studentIds)
                ->get(['student_id', 'period']);

            foreach ($existing as $inv) {
                if ($inv->period !== null && isset($periodLookup[$inv->period->toDateString()])) {
                    $existingKeys["{$inv->student_id}:{$inv->period->toDateString()}"] = true;
                }
            }
        }

        $items = [];
        $willCreate = 0;
        $alreadyExists = 0;
        $skippedInactive = 0;
        $totalAmount = 0;

        foreach ($students as $student) {
            $classroomName = $student->enrollments->first()?->classroom->name;
            $isActive = $student->status === StudentStatus::Active;

            foreach ($periods as $period) {
                $periodDate = $period->toDateString();
                $key = "{$student->id}:{$periodDate}";
                $amount = $overrides[$key] ?? $defaultAmount;
                $dueDate = BillingPeriod::dueDateForPeriod($period, $dueDay, $fixedDueDate);
                $description = BillingPeriod::descriptionFor(
                    $feeType->name,
                    $feeType->recurrence,
                    $period,
                    $activeYear?->name,
                );

                if (! $isActive) {
                    $state = 'skipped_inactive';
                    $stateLabel = 'Dilewati (nonaktif)';
                    $skippedInactive++;
                } elseif (isset($existingKeys[$key])) {
                    $state = 'already_exists';
                    $stateLabel = 'Sudah ada';
                    $alreadyExists++;
                } else {
                    $state = 'will_create';
                    $stateLabel = 'Akan dibuat';
                    $willCreate++;
                    $totalAmount += $amount;
                }

                $items[] = [
                    'key' => $key,
                    'student_id' => $student->id,
                    'student_name' => $student->name,
                    'nis' => $student->nis,
                    'classroom_name' => $classroomName,
                    'period' => $periodDate,
                    'period_label' => $feeType->recurrence === FeeRecurrence::Once
                        ? ($activeYear !== null ? $activeYear->name : $periodDate)
                        : $period->translatedFormat('M Y'),
                    'description' => $description,
                    'due_date' => $dueDate->toDateString(),
                    'amount' => $amount,
                    'state' => $state,
                    'state_label' => $stateLabel,
                ];
            }
        }

        return [
            'items' => $items,
            'summary' => [
                'will_create' => $willCreate,
                'already_exists' => $alreadyExists,
                'skipped_inactive' => $skippedInactive,
                'total_amount' => $totalAmount,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data): BillingRun
    {
        $resolved = $this->resolveContext($data);
        /** @var Collection<int, Student> $students */
        $students = $resolved['students'];
        /** @var list<CarbonImmutable> $periods */
        $periods = $resolved['periods'];
        /** @var FeeType $feeType */
        $feeType = $resolved['fee_type'];
        /** @var AcademicYear|null $activeYear */
        $activeYear = $resolved['active_year'];
        $defaultAmount = $resolved['default_amount'];
        $overrides = $resolved['overrides'];
        $dueDay = $resolved['due_day'];
        $fixedDueDate = $resolved['fixed_due_date'];
        /** @var BillingTargetType $targetType */
        $targetType = $resolved['target_type'];
        /** @var array<string, mixed> $targetPayload */
        $targetPayload = $resolved['target_payload'];

        $activeStudents = $students->filter(
            static fn (Student $s): bool => $s->status === StudentStatus::Active,
        );

        $candidates = [];
        foreach ($activeStudents as $student) {
            foreach ($periods as $period) {
                $periodDate = $period->toDateString();
                $key = "{$student->id}:{$periodDate}";
                $amount = $overrides[$key] ?? $defaultAmount;
                $dueDate = BillingPeriod::dueDateForPeriod($period, $dueDay, $fixedDueDate);
                $description = BillingPeriod::descriptionFor(
                    $feeType->name,
                    $feeType->recurrence,
                    $period,
                    $activeYear?->name,
                );

                $candidates[] = [
                    'student_id' => $student->id,
                    'fee_type_id' => $feeType->id,
                    'period' => $periodDate,
                    'description' => $description,
                    'amount' => $amount,
                    'due_date' => $dueDate->toDateString(),
                ];
            }
        }

        $run = DB::transaction(fn (): BillingRun => BillingRun::query()->create([
            'fee_type_id' => $feeType->id,
            'target_type' => $targetType->value,
            'target_payload' => $targetPayload,
            'period_from' => $periods[0]->toDateString(),
            'period_to' => $periods[count($periods) - 1]->toDateString(),
            'due_day' => $dueDay,
            'due_date' => $fixedDueDate?->toDateString(),
            'amount' => $defaultAmount,
            'created_by' => $actor->id,
            'invoice_count' => 0,
            'total_amount' => 0,
            'created_at' => now(),
        ]));

        $createdCount = 0;
        $createdTotal = 0;

        foreach (array_chunk($candidates, 100) as $chunk) {
            DB::transaction(function () use ($actor, $chunk, $run, $feeType, &$createdCount, &$createdTotal): void {
                $studentIds = array_values(array_unique(array_column($chunk, 'student_id')));
                $periodLookup = array_flip(array_values(array_unique(array_column($chunk, 'period'))));

                $existing = Invoice::query()
                    ->where('fee_type_id', $feeType->id)
                    ->whereIn('student_id', $studentIds)
                    ->lockForUpdate()
                    ->get(['student_id', 'period']);

                $existingMap = [];
                foreach ($existing as $inv) {
                    if ($inv->period !== null && isset($periodLookup[$inv->period->toDateString()])) {
                        $existingMap["{$inv->student_id}:{$inv->period->toDateString()}"] = true;
                    }
                }

                $discountsByStudent = StudentDiscount::query()
                    ->where('fee_type_id', $feeType->id)
                    ->whereIn('student_id', $studentIds)
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->get()
                    ->groupBy('student_id');

                foreach ($chunk as $item) {
                    $key = "{$item['student_id']}:{$item['period']}";
                    if (isset($existingMap[$key])) {
                        continue;
                    }

                    $invoice = Invoice::query()->create([
                        'student_id' => $item['student_id'],
                        'fee_type_id' => $item['fee_type_id'],
                        'billing_run_id' => $run->id,
                        'period' => $item['period'],
                        'description' => $item['description'],
                        'amount' => $item['amount'],
                        'adjustment_total' => 0,
                        'paid_total' => 0,
                        'due_date' => $item['due_date'],
                        'status' => InvoiceStatus::Unpaid,
                    ]);

                    $periodDate = CarbonImmutable::parse($item['period']);
                    $appliedDiscount = 0;

                    /** @var Collection<int, StudentDiscount> $studentDiscounts */
                    $studentDiscounts = $discountsByStudent->get($item['student_id'], new Collection);
                    foreach ($studentDiscounts as $discount) {
                        if (! $discount->isValidOn($periodDate)) {
                            continue;
                        }

                        $remainingBudget = max(0, $invoice->amount - $appliedDiscount);
                        if ($remainingBudget === 0) {
                            break;
                        }

                        $cut = min($remainingBudget, $discount->computeAmount($invoice->amount));
                        if ($cut <= 0) {
                            continue;
                        }

                        InvoiceAdjustment::query()->create([
                            'invoice_id' => $invoice->id,
                            'type' => InvoiceAdjustmentType::Discount,
                            'amount' => $cut,
                            'reason' => "Potongan otomatis: {$discount->reason}",
                            'created_by' => $actor->id,
                            'created_at' => now(),
                        ]);

                        $appliedDiscount += $cut;
                    }

                    if ($appliedDiscount > 0) {
                        $invoice->update([
                            'adjustment_total' => $appliedDiscount,
                            'status' => InvoiceStatusResolver::resolve($invoice->amount, $appliedDiscount, 0),
                        ]);
                    }

                    $existingMap[$key] = true;
                    $createdCount++;
                    $createdTotal += max(0, $item['amount'] - $appliedDiscount);
                }
            });
        }

        return DB::transaction(function () use ($actor, $run, $createdCount, $createdTotal): BillingRun {
            $run->update([
                'invoice_count' => $createdCount,
                'total_amount' => $createdTotal,
            ]);

            $this->audit->record(
                action: 'billing_run.executed',
                subject: $run,
                after: [
                    'fee_type_id' => $run->fee_type_id,
                    'target_type' => $run->target_type->value,
                    'invoice_count' => $createdCount,
                    'total_amount' => $createdTotal,
                ],
                userId: $actor->id,
            );

            return $run;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *   fee_type: FeeType,
     *   active_year: AcademicYear|null,
     *   students: Collection<int, Student>,
     *   periods: list<CarbonImmutable>,
     *   default_amount: int,
     *   overrides: array<string, int>,
     *   due_day: int|null,
     *   fixed_due_date: CarbonImmutable|null,
     *   target_type: BillingTargetType,
     *   target_payload: array<string, mixed>
     * }
     */
    private function resolveContext(array $data): array
    {
        $feeType = FeeType::query()->findOrFail((int) $data['fee_type_id']);
        $activeYear = AcademicYear::query()->where('is_active', true)->first();

        if ($feeType->recurrence === FeeRecurrence::Once) {
            if ($activeYear === null) {
                throw ValidationException::withMessages([
                    'fee_type_id' => 'Aktifkan tahun ajaran terlebih dahulu sebelum menerbitkan tagihan sekali.',
                ]);
            }

            $periods = [$activeYear->starts_on];
        } else {
            $from = CarbonImmutable::parse((string) ($data['period_from'] ?? '').'-01');
            $to = CarbonImmutable::parse((string) ($data['period_to'] ?? '').'-01');

            try {
                $periods = BillingPeriod::expandMonthly($from, $to);
            } catch (InvalidArgumentException $e) {
                throw ValidationException::withMessages([
                    'period_to' => $e->getMessage(),
                ]);
            }
        }

        $dueMode = (string) ($data['due_mode'] ?? 'day');
        $dueDay = $dueMode === 'day' ? (int) ($data['due_day'] ?? 10) : null;
        $fixedDueDate = $dueMode === 'date' && ! empty($data['due_date'])
            ? CarbonImmutable::parse((string) $data['due_date'])
            : null;

        if ($dueMode === 'date' && $fixedDueDate === null) {
            throw ValidationException::withMessages([
                'due_date' => 'Tanggal jatuh tempo wajib diisi.',
            ]);
        }

        $targetType = BillingTargetType::from((string) $data['target_type']);
        [$students, $targetPayload] = $this->resolveStudents($targetType, $data, $activeYear, $feeType);

        $defaultAmount = isset($data['default_amount']) ? (int) $data['default_amount'] : $feeType->default_amount;
        $rawOverrides = is_array($data['overrides'] ?? null) ? $data['overrides'] : [];
        $overrides = [];
        foreach ($rawOverrides as $k => $v) {
            if (is_string($k) && is_numeric($v) && (int) $v >= 0) {
                $overrides[$k] = (int) $v;
            }
        }

        return [
            'fee_type' => $feeType,
            'active_year' => $activeYear,
            'students' => $students,
            'periods' => $periods,
            'default_amount' => max(0, $defaultAmount),
            'overrides' => $overrides,
            'due_day' => $dueDay,
            'fixed_due_date' => $fixedDueDate,
            'target_type' => $targetType,
            'target_payload' => $targetPayload,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: Collection<int, Student>, 1: array<string, mixed>}
     */
    private function resolveStudents(
        BillingTargetType $targetType,
        array $data,
        ?AcademicYear $activeYear,
        FeeType $feeType,
    ): array {
        $baseQuery = Student::query()->with([
            'enrollments' => function ($q) use ($activeYear): void {
                if ($activeYear !== null) {
                    $q->where('academic_year_id', $activeYear->id)->with('classroom:id,name,grade');
                }
            },
        ])->orderBy('name');

        return match ($targetType) {
            BillingTargetType::Unit => (function () use ($baseQuery, $data, $feeType): array {
                $unitId = ! empty($data['unit_id']) ? (int) $data['unit_id'] : $feeType->unit_id;
                if ($unitId === null) {
                    throw ValidationException::withMessages(['unit_id' => 'Pilih unit tujuan.']);
                }

                return [
                    $baseQuery->where('unit_id', $unitId)->get(),
                    ['unit_id' => $unitId],
                ];
            })(),
            BillingTargetType::Grade => (function () use ($baseQuery, $data, $activeYear, $feeType): array {
                $unitId = ! empty($data['unit_id']) ? (int) $data['unit_id'] : $feeType->unit_id;
                $grade = trim((string) ($data['grade'] ?? ''));

                if ($activeYear === null || $grade === '') {
                    throw ValidationException::withMessages(['grade' => 'Pilih tingkat dan pastikan ada tahun ajaran aktif.']);
                }

                $classroomIds = Classroom::query()
                    ->where('academic_year_id', $activeYear->id)
                    ->where('grade', $grade)
                    ->when($unitId !== null, fn ($q) => $q->where('unit_id', $unitId))
                    ->pluck('id')
                    ->all();

                $students = $classroomIds === []
                    ? new Collection
                    : $baseQuery->whereHas('enrollments', fn ($q) => $q
                        ->where('academic_year_id', $activeYear->id)
                        ->whereIn('classroom_id', $classroomIds))->get();

                return [$students, ['unit_id' => $unitId, 'grade' => $grade]];
            })(),
            BillingTargetType::Classroom => (function () use ($baseQuery, $data, $activeYear): array {
                $classroomId = ! empty($data['classroom_id']) ? (int) $data['classroom_id'] : null;
                if ($classroomId === null) {
                    throw ValidationException::withMessages(['classroom_id' => 'Pilih rombel tujuan.']);
                }

                $classroom = Classroom::query()->findOrFail($classroomId);
                $yearId = $activeYear !== null ? $activeYear->id : $classroom->academic_year_id;

                $students = $baseQuery->whereHas('enrollments', fn ($q) => $q
                    ->where('academic_year_id', $yearId)
                    ->where('classroom_id', $classroom->id))->get();

                return [$students, ['classroom_id' => $classroom->id]];
            })(),
            BillingTargetType::Students => (function () use ($baseQuery, $data): array {
                $ids = array_values(array_unique(array_map('intval', (array) ($data['student_ids'] ?? []))));
                if ($ids === []) {
                    throw ValidationException::withMessages(['student_ids' => 'Pilih minimal satu siswa.']);
                }

                return [
                    $baseQuery->whereIn('id', $ids)->get(),
                    ['student_ids' => $ids],
                ];
            })(),
        };
    }
}
