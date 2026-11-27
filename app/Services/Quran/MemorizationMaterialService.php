<?php

declare(strict_types=1);

namespace App\Services\Quran;

use App\Models\MemorizationMaterial;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MemorizationMaterialService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): MemorizationMaterial
    {
        return DB::transaction(function () use ($actor, $data): MemorizationMaterial {
            $material = MemorizationMaterial::query()->create([
                'unit_id' => isset($data['unit_id']) && $data['unit_id'] !== '' ? (int) $data['unit_id'] : null,
                'kind' => (string) $data['kind'],
                'title' => trim((string) $data['title']),
                'arabic_text' => isset($data['arabic_text']) && trim((string) $data['arabic_text']) !== ''
                    ? trim((string) $data['arabic_text'])
                    : null,
                'translation' => isset($data['translation']) && trim((string) $data['translation']) !== ''
                    ? trim((string) $data['translation'])
                    : null,
                'source' => isset($data['source']) && trim((string) $data['source']) !== ''
                    ? trim((string) $data['source'])
                    : null,
                'sort_order' => max(1, (int) ($data['sort_order'] ?? 1)),
                'is_active' => true,
            ]);

            $this->audit->record(
                action: 'quran_material.created',
                subject: $material,
                after: $material->only(['unit_id', 'kind', 'title', 'sort_order']),
                userId: $actor->id,
            );

            return $material;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, MemorizationMaterial $material, array $data): MemorizationMaterial
    {
        return DB::transaction(function () use ($actor, $material, $data): MemorizationMaterial {
            $before = $material->only(['unit_id', 'kind', 'title', 'sort_order']);
            $material->update([
                'unit_id' => isset($data['unit_id']) && $data['unit_id'] !== '' ? (int) $data['unit_id'] : null,
                'kind' => (string) $data['kind'],
                'title' => trim((string) $data['title']),
                'arabic_text' => isset($data['arabic_text']) && trim((string) $data['arabic_text']) !== ''
                    ? trim((string) $data['arabic_text'])
                    : null,
                'translation' => isset($data['translation']) && trim((string) $data['translation']) !== ''
                    ? trim((string) $data['translation'])
                    : null,
                'source' => isset($data['source']) && trim((string) $data['source']) !== ''
                    ? trim((string) $data['source'])
                    : null,
                'sort_order' => max(1, (int) ($data['sort_order'] ?? 1)),
            ]);

            $this->audit->record(
                action: 'quran_material.updated',
                subject: $material,
                before: $before,
                after: $material->only(['unit_id', 'kind', 'title', 'sort_order']),
                userId: $actor->id,
            );

            return $material;
        });
    }

    public function toggleActive(User $actor, MemorizationMaterial $material, bool $isActive): MemorizationMaterial
    {
        return DB::transaction(function () use ($actor, $material, $isActive): MemorizationMaterial {
            $material->update(['is_active' => $isActive]);
            $this->audit->record(
                action: $isActive ? 'quran_material.activated' : 'quran_material.deactivated',
                subject: $material,
                after: ['is_active' => $isActive],
                userId: $actor->id,
            );

            return $material;
        });
    }

    public function delete(User $actor, MemorizationMaterial $material): bool
    {
        return DB::transaction(function () use ($actor, $material): bool {
            $hasRecords = Schema::hasTable('memorization_records')
                && DB::table('memorization_records')->where('memorization_material_id', $material->id)->exists();

            if ($hasRecords) {
                $material->update(['is_active' => false]);
                $this->audit->record(
                    action: 'quran_material.deactivated',
                    subject: $material,
                    after: ['reason' => 'has_records'],
                    userId: $actor->id,
                );

                return false;
            }

            $this->audit->record(
                action: 'quran_material.deleted',
                subject: $material,
                before: $material->only(['kind', 'title']),
                userId: $actor->id,
            );
            $material->delete();

            return true;
        });
    }
}
