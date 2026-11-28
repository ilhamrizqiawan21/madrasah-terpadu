<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\ExamDelivery;
use App\Enums\ExamPeriodStatus;
use App\Enums\ExamRequirementScope;
use App\Models\ExamPeriod;
use App\Models\FeeType;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pengelola periode ujian dan syarat tagihannya (T-611, ADR-016).
 */
final class ExamPeriodService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{
     *     unit_id: int,
     *     name: string,
     *     delivery: string,
     *     starts_on: string,
     *     ends_on: string,
     *     requirements: list<array{fee_type_id: int, invoice_scope: string, period_until?: ?string}>,
     * }  $data
     */
    public function create(User $actor, array $data): ExamPeriod
    {
        $normalizedReqs = $this->normalizeRequirements((int) $data['unit_id'], $data['requirements']);

        return DB::transaction(function () use ($actor, $data, $normalizedReqs): ExamPeriod {
            $period = ExamPeriod::query()->create([
                'unit_id' => (int) $data['unit_id'],
                'name' => trim($data['name']),
                'delivery' => ExamDelivery::from($data['delivery']),
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'],
                'status' => ExamPeriodStatus::Draft,
                'created_by' => $actor->id,
            ]);

            foreach ($normalizedReqs as $req) {
                $period->requirements()->create($req);
            }

            $this->audit->record('exam_period.created', $period, null, [
                'name' => $period->name,
                'unit_id' => $period->unit_id,
                'delivery' => $period->delivery->value,
                'requirements_count' => count($normalizedReqs),
            ]);

            return $period->load(['unit:id,code,name', 'requirements.feeType:id,name']);
        });
    }

    /**
     * @param  array{
     *     name: string,
     *     delivery: string,
     *     starts_on: string,
     *     ends_on: string,
     *     requirements: list<array{fee_type_id: int, invoice_scope: string, period_until?: ?string}>,
     * }  $data
     */
    public function update(User $actor, ExamPeriod $period, array $data): ExamPeriod
    {
        $normalizedReqs = $this->normalizeRequirements($period->unit_id, $data['requirements']);

        return DB::transaction(function () use ($period, $data, $normalizedReqs): ExamPeriod {
            $locked = ExamPeriod::query()->whereKey($period->id)->lockForUpdate()->firstOrFail();

            $before = [
                'name' => $locked->name,
                'delivery' => $locked->delivery->value,
                'starts_on' => $locked->starts_on->toDateString(),
                'ends_on' => $locked->ends_on->toDateString(),
            ];

            $locked->forceFill([
                'name' => trim($data['name']),
                'delivery' => ExamDelivery::from($data['delivery']),
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'],
            ])->save();

            $locked->requirements()->delete();
            foreach ($normalizedReqs as $req) {
                $locked->requirements()->create($req);
            }

            $this->audit->record('exam_period.updated', $locked, $before, [
                'name' => $locked->name,
                'delivery' => $locked->delivery->value,
                'starts_on' => $locked->starts_on->toDateString(),
                'ends_on' => $locked->ends_on->toDateString(),
                'requirements_count' => count($normalizedReqs),
            ]);

            return $locked->load(['unit:id,code,name', 'requirements.feeType:id,name']);
        });
    }

    public function activate(User $actor, ExamPeriod $period): ExamPeriod
    {
        return $this->transition($period, ExamPeriodStatus::Active, 'exam_period.activated');
    }

    public function close(User $actor, ExamPeriod $period): ExamPeriod
    {
        return $this->transition($period, ExamPeriodStatus::Closed, 'exam_period.closed');
    }

    private function transition(ExamPeriod $period, ExamPeriodStatus $next, string $action): ExamPeriod
    {
        return DB::transaction(function () use ($period, $next, $action): ExamPeriod {
            $locked = ExamPeriod::query()->whereKey($period->id)->lockForUpdate()->firstOrFail();
            $before = ['status' => $locked->status->value];

            if ($locked->status === $next) {
                return $locked;
            }

            $locked->forceFill(['status' => $next])->save();

            $this->audit->record($action, $locked, $before, ['status' => $next->value]);

            return $locked;
        });
    }

    /**
     * @param  list<array{fee_type_id: int, invoice_scope: string, period_until?: ?string}>  $requirements
     * @return list<array{fee_type_id: int, invoice_scope: ExamRequirementScope, period_until: ?string}>
     */
    private function normalizeRequirements(int $unitId, array $requirements): array
    {
        $seen = [];
        $result = [];

        $feeTypes = FeeType::query()
            ->whereIn('id', array_map(static fn (array $r): int => (int) $r['fee_type_id'], $requirements))
            ->get()
            ->keyBy('id');

        foreach ($requirements as $index => $row) {
            $feeTypeId = (int) $row['fee_type_id'];
            if (isset($seen[$feeTypeId])) {
                throw ValidationException::withMessages([
                    "requirements.{$index}.fee_type_id" => 'Jenis tagihan tidak boleh ganda dalam satu periode.',
                ]);
            }
            $seen[$feeTypeId] = true;

            /** @var FeeType|null $feeType */
            $feeType = $feeTypes->get($feeTypeId);
            if ($feeType === null || ($feeType->unit_id !== null && $feeType->unit_id !== $unitId)) {
                throw ValidationException::withMessages([
                    "requirements.{$index}.fee_type_id" => 'Jenis tagihan harus berasal dari unit yang sama.',
                ]);
            }

            $scope = ExamRequirementScope::from($row['invoice_scope']);
            $periodUntilRaw = $row['period_until'] ?? null;

            if ($scope === ExamRequirementScope::UpToPeriod) {
                if ($periodUntilRaw === null || trim($periodUntilRaw) === '') {
                    throw ValidationException::withMessages([
                        "requirements.{$index}.period_until" => 'Bulan batas wajib diisi untuk cakupan sampai bulan batas.',
                    ]);
                }

                $periodUntil = CarbonImmutable::parse($periodUntilRaw)->startOfMonth()->toDateString();
            } else {
                $periodUntil = null;
            }

            $result[] = [
                'fee_type_id' => $feeTypeId,
                'invoice_scope' => $scope,
                'period_until' => $periodUntil,
            ];
        }

        return $result;
    }
}
