<?php

declare(strict_types=1);

namespace App\Services\Quran;

use App\Models\PibChapter;
use App\Models\PibMaterial;
use App\Models\PibSubchapter;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class PibCurriculumService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function createChapter(User $actor, ?int $unitId, string $name): PibChapter
    {
        return DB::transaction(function () use ($actor, $unitId, $name): PibChapter {
            $order = ((int) PibChapter::query()->max('sort_order')) + 1;
            $chapter = PibChapter::query()->create([
                'unit_id' => $unitId,
                'name' => trim($name),
                'sort_order' => $order,
            ]);

            $this->audit->record('pib_chapter.created', $chapter, null, ['name' => $chapter->name], $actor->id);

            return $chapter;
        });
    }

    public function createSubchapter(User $actor, PibChapter $chapter, string $name): PibSubchapter
    {
        return DB::transaction(function () use ($actor, $chapter, $name): PibSubchapter {
            $order = ((int) $chapter->subchapters()->max('sort_order')) + 1;
            $sub = PibSubchapter::query()->create([
                'pib_chapter_id' => $chapter->id,
                'name' => trim($name),
                'sort_order' => $order,
            ]);

            $this->audit->record('pib_subchapter.created', $sub, null, ['name' => $sub->name], $actor->id);

            return $sub;
        });
    }

    public function createMaterial(User $actor, PibSubchapter $subchapter, string $name, float $weight): PibMaterial
    {
        return DB::transaction(function () use ($actor, $subchapter, $name, $weight): PibMaterial {
            $order = ((int) $subchapter->materials()->max('sort_order')) + 1;
            $material = PibMaterial::query()->create([
                'pib_subchapter_id' => $subchapter->id,
                'name' => trim($name),
                'weight' => $weight,
                'sort_order' => $order,
            ]);

            $this->audit->record('pib_material.created', $material, null, ['name' => $material->name, 'weight' => $weight], $actor->id);

            return $material;
        });
    }
}
