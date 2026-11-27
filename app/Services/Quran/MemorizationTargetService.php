<?php

declare(strict_types=1);

namespace App\Services\Quran;

use App\Domain\Quran\AyahRange;
use App\Models\MemorizationMaterial;
use App\Models\MemorizationTarget;
use App\Models\Surah;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class MemorizationTargetService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): MemorizationTarget
    {
        $payload = $this->validatePayload($data);

        return DB::transaction(function () use ($actor, $payload): MemorizationTarget {
            $target = MemorizationTarget::query()->create($payload);

            $this->audit->record(
                action: 'quran_target.created',
                subject: $target,
                after: $target->only(['unit_id', 'grade', 'academic_year_id', 'kind', 'title']),
                userId: $actor->id,
            );

            return $target;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, MemorizationTarget $target, array $data): MemorizationTarget
    {
        $payload = $this->validatePayload($data);

        return DB::transaction(function () use ($actor, $target, $payload): MemorizationTarget {
            $before = $target->only(['unit_id', 'grade', 'academic_year_id', 'kind', 'title']);
            $target->update($payload);

            $this->audit->record(
                action: 'quran_target.updated',
                subject: $target,
                before: $before,
                after: $target->only(['unit_id', 'grade', 'academic_year_id', 'kind', 'title']),
                userId: $actor->id,
            );

            return $target;
        });
    }

    public function toggleActive(User $actor, MemorizationTarget $target, bool $isActive): MemorizationTarget
    {
        return DB::transaction(function () use ($actor, $target, $isActive): MemorizationTarget {
            $target->update(['is_active' => $isActive]);
            $this->audit->record(
                action: $isActive ? 'quran_target.activated' : 'quran_target.deactivated',
                subject: $target,
                after: ['is_active' => $isActive],
                userId: $actor->id,
            );

            return $target;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validatePayload(array $data): array
    {
        $kind = (string) ($data['kind'] ?? 'quran');
        $ranges = null;
        $materialIds = null;

        if ($kind === 'quran') {
            /** @var list<array{surah: int, ayah_from: int, ayah_to: int}> $rawRanges */
            $rawRanges = is_array($data['ranges'] ?? null) ? $data['ranges'] : [];
            if ($rawRanges === []) {
                throw ValidationException::withMessages([
                    'ranges' => 'Tambahkan minimal satu rentang surah dan ayat.',
                ]);
            }

            $surahCounts = Surah::query()->pluck('ayah_count', 'number')->all();
            $normalized = [];

            foreach ($rawRanges as $r) {
                $surah = (int) $r['surah'];
                $from = (int) $r['ayah_from'];
                $to = (int) $r['ayah_to'];

                if (! isset($surahCounts[$surah])) {
                    throw ValidationException::withMessages([
                        'ranges' => "Surah nomor {$surah} tidak ditemukan.",
                    ]);
                }

                try {
                    AyahRange::validate($surah, $from, $to, (int) $surahCounts[$surah]);
                } catch (InvalidArgumentException $e) {
                    throw ValidationException::withMessages([
                        'ranges' => $e->getMessage(),
                    ]);
                }

                $normalized[] = [
                    'surah' => $surah,
                    'ayah_from' => $from,
                    'ayah_to' => $to,
                ];
            }

            $ranges = $normalized;
        } else {
            /** @var list<int> $rawIds */
            $rawIds = array_values(array_unique(array_map('intval', is_array($data['material_ids'] ?? null) ? $data['material_ids'] : [])));
            if ($rawIds === []) {
                throw ValidationException::withMessages([
                    'material_ids' => 'Pilih minimal satu materi hadits atau doa.',
                ]);
            }

            $validCount = MemorizationMaterial::query()
                ->whereIn('id', $rawIds)
                ->where('kind', $kind)
                ->count();

            if ($validCount !== count($rawIds)) {
                throw ValidationException::withMessages([
                    'material_ids' => 'Terdapat materi yang tidak valid atau tidak sesuai jenis.',
                ]);
            }

            $materialIds = $rawIds;
        }

        return [
            'unit_id' => (int) $data['unit_id'],
            'grade' => trim((string) $data['grade']),
            'academic_year_id' => (int) $data['academic_year_id'],
            'kind' => $kind,
            'title' => trim((string) $data['title']),
            'ranges' => $ranges,
            'material_ids' => $materialIds,
            'is_active' => true,
        ];
    }
}
