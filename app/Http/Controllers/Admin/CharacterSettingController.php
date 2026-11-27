<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CharacterSource;
use App\Enums\Curriculum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Character\ApplyCurriculumRequest;
use App\Http\Requests\Admin\Character\SaveDimensionRequest;
use App\Http\Requests\Admin\Character\UpdateScaleRequest;
use App\Models\CharacterComponent;
use App\Models\CharacterDimension;
use App\Models\CharacterProfile;
use App\Models\HabitItem;
use App\Models\Unit;
use App\Models\User;
use App\Services\Character\CharacterSettingsService;
use App\Support\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengaturan > Penilaian karakter (ADR-029, T-1201): kurikulum, skala capaian, dimensi & komponen per unit.
 */
class CharacterSettingController extends Controller
{
    public function __construct(private readonly CharacterSettingsService $service) {}

    public function edit(Request $request): Response
    {
        $units = Unit::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);
        $requested = $request->string('unit')->toString();
        $contextUnit = SchoolContext::resolve($request->session())['unit'];
        $unit = $units->firstWhere('code', $requested !== '' ? $requested : $contextUnit) ?? $units->first();

        $profile = $unit !== null ? CharacterProfile::query()->where('unit_id', $unit->id)->first() : null;

        $dimensions = $unit !== null
            ? CharacterDimension::query()
                ->with('components')
                ->where('unit_id', $unit->id)
                ->orderByDesc('is_active')
                ->orderBy('position')
                ->orderBy('id')
                ->get()
            : collect();

        $habitItems = $unit !== null
            ? HabitItem::query()->forUnit($unit->id)->orderBy('aspect')->orderBy('id')->get(['id', 'name', 'aspect', 'is_active'])
            : collect();

        return Inertia::render('admin/settings/character', [
            'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'code' => $u->code, 'name' => $u->name])->values()->all(),
            'unit' => $unit !== null ? ['id' => $unit->id, 'code' => $unit->code, 'name' => $unit->name] : null,
            'profile' => $profile !== null ? [
                'curriculum' => $profile->curriculum->value,
                'curriculum_label' => $profile->curriculum->label(),
                'scale' => $profile->scale,
                'coverage_min_pct' => $profile->coverage_min_pct,
            ] : null,
            'dimensions' => $dimensions->map(fn (CharacterDimension $d): array => [
                'id' => $d->id,
                'code' => $d->code,
                'name' => $d->name,
                'is_active' => $d->is_active,
                'description_templates' => $d->description_templates,
                'components' => $d->components->map(fn (CharacterComponent $c): array => [
                    'source' => $c->source->value,
                    'weight' => $c->weight,
                    'params' => $c->params,
                ])->values()->all(),
            ])->values()->all(),
            'habitItems' => $habitItems->map(fn (HabitItem $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'aspect' => $item->aspect->value,
                'is_active' => $item->is_active,
            ])->values()->all(),
            'sources' => array_map(fn (CharacterSource $s): array => [
                'value' => $s->value,
                'label' => $s->label(),
                'description' => $s->description(),
                'needs_coverage' => $s->needsCoverage(),
                'uses_tiers' => $s->usesTiers(),
                'default_tiers' => $s->defaultTiers(),
            ], CharacterSource::cases()),
            'curricula' => array_map(fn (Curriculum $c): array => [
                'value' => $c->value,
                'label' => $c->label(),
                'scale' => $c->defaultScale(),
                'dimensions' => array_column($c->defaultDimensions(), 'name'),
            ], Curriculum::cases()),
        ]);
    }

    public function applyCurriculum(ApplyCurriculumRequest $request, Unit $unit): RedirectResponse
    {
        $this->service->applyCurriculum($unit, $request->curriculum(), $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Kurikulum unit {$unit->code} diterapkan dengan dimensi bawaan."]);

        return to_route('admin.settings.character.edit', ['unit' => $unit->code]);
    }

    public function updateScale(UpdateScaleRequest $request, Unit $unit): RedirectResponse
    {
        $profile = CharacterProfile::query()->where('unit_id', $unit->id)->firstOrFail();

        $this->service->updateScale($profile, $request->scale(), $request->coverage(), $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Skala capaian disimpan.']);

        return to_route('admin.settings.character.edit', ['unit' => $unit->code]);
    }

    public function storeDimension(SaveDimensionRequest $request, Unit $unit): RedirectResponse
    {
        $this->service->saveDimension($unit, null, $request->dimensionData(), $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dimensi ditambahkan.']);

        return to_route('admin.settings.character.edit', ['unit' => $unit->code]);
    }

    public function updateDimension(SaveDimensionRequest $request, Unit $unit, CharacterDimension $dimension): RedirectResponse
    {
        abort_unless($dimension->unit_id === $unit->id, 404);

        $this->service->saveDimension($unit, $dimension, $request->dimensionData(), $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dimensi diperbarui.']);

        return to_route('admin.settings.character.edit', ['unit' => $unit->code]);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
