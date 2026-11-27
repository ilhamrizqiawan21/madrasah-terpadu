<?php

declare(strict_types=1);

namespace App\Services\Quran;

use App\Models\Rubric;
use App\Models\RubricVersion;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RubricService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  list<array{name: string, mode: string, weight: float|int, max_score: int, deduction_per_error: float|int, pass_threshold?: float|int}>  $criteria
     */
    public function createDraftVersion(User $actor, Rubric $rubric, float $passThreshold, array $criteria): RubricVersion
    {
        if ($criteria === []) {
            throw ValidationException::withMessages(['criteria' => 'Minimal harus ada satu kriteria penilaian.']);
        }

        return DB::transaction(function () use ($actor, $rubric, $passThreshold, $criteria): RubricVersion {
            $nextNumber = ((int) $rubric->versions()->max('version_number')) + 1;

            $version = RubricVersion::query()->create([
                'rubric_id' => $rubric->id,
                'version_number' => $nextNumber,
                'pass_threshold' => $passThreshold,
                'published_at' => null,
            ]);

            $this->syncCriteria($version, $criteria);

            $this->audit->record(
                action: 'rubric_version.created',
                subject: $version,
                after: ['rubric_id' => $rubric->id, 'version_number' => $nextNumber],
                userId: $actor->id,
            );

            return $version;
        });
    }

    /**
     * @param  list<array{name: string, mode: string, weight: float|int, max_score: int, deduction_per_error: float|int, pass_threshold?: float|int}>  $criteria
     */
    public function updateDraftCriteria(User $actor, RubricVersion $version, array $criteria): RubricVersion
    {
        if ($version->published_at !== null) {
            throw ValidationException::withMessages([
                'version' => 'Versi rubrik yang sudah diterbitkan tidak dapat diubah.',
            ]);
        }

        if ($criteria === []) {
            throw ValidationException::withMessages(['criteria' => 'Minimal harus ada satu kriteria penilaian.']);
        }

        return DB::transaction(function () use ($actor, $version, $criteria): RubricVersion {
            /** @var RubricVersion $locked */
            $locked = RubricVersion::query()->lockForUpdate()->findOrFail($version->id);
            if ($locked->published_at !== null) {
                throw ValidationException::withMessages([
                    'version' => 'Versi rubrik yang sudah diterbitkan tidak dapat diubah.',
                ]);
            }

            $locked->criteria()->delete();
            $this->syncCriteria($locked, $criteria);

            $this->audit->record(
                action: 'rubric_version.updated',
                subject: $locked,
                after: ['criteria_count' => count($criteria)],
                userId: $actor->id,
            );

            return $locked;
        });
    }

    public function publish(User $actor, RubricVersion $version): RubricVersion
    {
        if ($version->published_at !== null) {
            throw ValidationException::withMessages([
                'version' => 'Versi rubrik ini sudah diterbitkan.',
            ]);
        }

        if ($version->criteria()->count() === 0) {
            throw ValidationException::withMessages([
                'version' => 'Rubrik tidak dapat diterbitkan tanpa kriteria.',
            ]);
        }

        return DB::transaction(function () use ($actor, $version): RubricVersion {
            /** @var RubricVersion $locked */
            $locked = RubricVersion::query()->lockForUpdate()->findOrFail($version->id);
            $locked->update([
                'published_at' => now(),
                'published_by' => $actor->id,
            ]);

            $this->audit->record(
                action: 'rubric_version.published',
                subject: $locked,
                after: ['published_at' => $locked->published_at?->toDateTimeString()],
                userId: $actor->id,
            );

            return $locked;
        });
    }

    /**
     * @param  list<array{name: string, mode: string, weight: float|int, max_score: int, deduction_per_error: float|int, pass_threshold?: float|int}>  $criteria
     */
    private function syncCriteria(RubricVersion $version, array $criteria): void
    {
        foreach ($criteria as $index => $c) {
            $version->criteria()->create([
                'name' => trim($c['name']),
                'mode' => $c['mode'],
                'weight' => (float) $c['weight'],
                'max_score' => (int) $c['max_score'],
                'deduction_per_error' => (float) $c['deduction_per_error'],
                'pass_threshold' => (float) ($c['pass_threshold'] ?? $version->pass_threshold),
                'sort_order' => $index + 1,
            ]);
        }
    }
}
