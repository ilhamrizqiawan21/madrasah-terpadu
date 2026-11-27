<?php

declare(strict_types=1);

namespace App\Services\Character;

use App\Enums\CharacterSource;
use App\Enums\Curriculum;
use App\Models\CharacterComponent;
use App\Models\CharacterDimension;
use App\Models\CharacterProfile;
use App\Models\Unit;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Satu-satunya penulis konfigurasi penilaian karakter per unit (ADR-029, T-1201).
 *
 * Dimensi tidak pernah dihapus: mengganti kurikulum menonaktifkan dimensi lama dan
 * mengaktifkan/ membuat dimensi bawaan kurikulum baru, sehingga hasil penilaian lama
 * tetap menunjuk dimensi yang ada.
 */
final class CharacterSettingsService
{
    public const MIN_LEVELS = 2;

    public const MAX_LEVELS = 6;

    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function applyCurriculum(Unit $unit, Curriculum $curriculum, User $actor): CharacterProfile
    {
        return DB::transaction(function () use ($unit, $curriculum, $actor): CharacterProfile {
            /** @var CharacterProfile|null $profile */
            $profile = CharacterProfile::query()->where('unit_id', $unit->id)->lockForUpdate()->first();
            $before = $profile?->only(['curriculum', 'scale', 'coverage_min_pct']);

            $profile ??= new CharacterProfile(['unit_id' => $unit->id, 'coverage_min_pct' => 60]);
            $profile->fill([
                'curriculum' => $curriculum,
                'scale' => $curriculum->defaultScale(),
                'updated_by' => $actor->id,
            ])->save();

            $defaultCodes = array_column($curriculum->defaultDimensions(), 'code');

            CharacterDimension::query()
                ->where('unit_id', $unit->id)
                ->whereNotIn('code', $defaultCodes)
                ->update(['is_active' => false]);

            foreach ($curriculum->defaultDimensions() as $position => $default) {
                $dimension = CharacterDimension::query()->firstOrNew(['unit_id' => $unit->id, 'code' => $default['code']]);
                $dimension->fill([
                    'name' => $default['name'],
                    'position' => $position + 1,
                    'is_active' => true,
                    'description_templates' => $curriculum->defaultTemplates(),
                ])->save();

                $dimension->components()->delete();

                foreach ($default['components'] as $component) {
                    $dimension->components()->create([
                        'source' => $component['source'],
                        'weight' => $component['weight'],
                        'params' => $component['params'],
                    ]);
                }
            }

            $this->auditLogger->record(
                action: 'character.curriculum_applied',
                subject: $profile,
                before: $before,
                after: ['curriculum' => $curriculum->value, 'scale' => $profile->scale, 'dimensions' => $defaultCodes],
                userId: $actor->id,
            );

            return $profile;
        });
    }

    /**
     * @param  list<array{code: string, label: string, min: int}>  $scale
     */
    public function updateScale(CharacterProfile $profile, array $scale, int $coverageMinPct, User $actor): CharacterProfile
    {
        $this->assertScale($scale);

        return DB::transaction(function () use ($profile, $scale, $coverageMinPct, $actor): CharacterProfile {
            $before = $profile->only(['scale', 'coverage_min_pct']);

            $profile->fill([
                'scale' => $scale,
                'coverage_min_pct' => $coverageMinPct,
                'updated_by' => $actor->id,
            ])->save();

            $this->auditLogger->record(
                action: 'character.scale_updated',
                subject: $profile,
                before: $before,
                after: $profile->only(['scale', 'coverage_min_pct']),
                userId: $actor->id,
            );

            return $profile;
        });
    }

    /**
     * @param  array{name: string, is_active: bool, description_templates: array<string, string>, components: list<array{source: string, weight: int, params: array<string, mixed>}>}  $data
     */
    public function saveDimension(Unit $unit, ?CharacterDimension $dimension, array $data, User $actor): CharacterDimension
    {
        $this->assertWeights($data['components']);

        return DB::transaction(function () use ($unit, $dimension, $data, $actor): CharacterDimension {
            $before = $dimension !== null ? $this->snapshot($dimension) : null;

            if ($dimension === null) {
                $position = (int) CharacterDimension::query()->where('unit_id', $unit->id)->max('position');
                $dimension = new CharacterDimension([
                    'unit_id' => $unit->id,
                    'code' => $this->uniqueCode($unit, $data['name']),
                    'position' => $position + 1,
                ]);
            }

            $dimension->fill([
                'name' => $data['name'],
                'is_active' => $data['is_active'],
                'description_templates' => $data['description_templates'],
            ])->save();

            $dimension->components()->delete();

            foreach ($data['components'] as $component) {
                $dimension->components()->create([
                    'source' => CharacterSource::from($component['source']),
                    'weight' => $component['weight'],
                    'params' => $component['params'],
                ]);
            }

            $dimension->load('components');

            $this->auditLogger->record(
                action: $before === null ? 'character.dimension_created' : 'character.dimension_updated',
                subject: $dimension,
                before: $before,
                after: $this->snapshot($dimension),
                userId: $actor->id,
            );

            return $dimension;
        });
    }

    /**
     * @param  list<array{code: string, label: string, min: int}>  $scale
     */
    private function assertScale(array $scale): void
    {
        $count = count($scale);

        if ($count < self::MIN_LEVELS || $count > self::MAX_LEVELS) {
            throw ValidationException::withMessages(['scale' => 'Skala capaian harus berisi 2 sampai 6 tingkat.']);
        }

        $codes = array_map(fn (array $level): string => mb_strtoupper($level['code']), $scale);

        if (count(array_unique($codes)) !== $count) {
            throw ValidationException::withMessages(['scale' => 'Kode tingkat capaian tidak boleh sama.']);
        }

        for ($i = 1; $i < $count; $i++) {
            if ($scale[$i]['min'] >= $scale[$i - 1]['min']) {
                throw ValidationException::withMessages(['scale' => 'Nilai minimum harus menurun dari tingkat tertinggi ke terendah.']);
            }
        }

        if ($scale[$count - 1]['min'] !== 0) {
            throw ValidationException::withMessages(['scale' => 'Tingkat terendah harus dimulai dari nilai 0.']);
        }
    }

    /**
     * @param  list<array{source: string, weight: int, params: array<string, mixed>}>  $components
     */
    private function assertWeights(array $components): void
    {
        $total = array_sum(array_column($components, 'weight'));

        if ($total !== 100) {
            throw ValidationException::withMessages(['components' => "Jumlah bobot komponen harus 100 (sekarang {$total})."]);
        }
    }

    private function uniqueCode(Unit $unit, string $name): string
    {
        $base = mb_substr(trim((string) preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($name)), '-'), 0, 24) ?: 'dimensi';
        $code = $base;
        $suffix = 2;

        while (CharacterDimension::query()->where('unit_id', $unit->id)->where('code', $code)->exists()) {
            $code = $base.'-'.$suffix++;
        }

        return $code;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(CharacterDimension $dimension): array
    {
        return [
            'name' => $dimension->name,
            'is_active' => $dimension->is_active,
            'description_templates' => $dimension->description_templates,
            'components' => $dimension->components
                ->map(fn (CharacterComponent $c): array => ['source' => $c->source->value, 'weight' => $c->weight, 'params' => $c->params])
                ->values()
                ->all(),
        ];
    }
}
