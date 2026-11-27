<?php

declare(strict_types=1);

use App\Enums\Curriculum;
use App\Enums\Feature;
use App\Enums\Role as RoleEnum;
use App\Models\AuditLog;
use App\Models\CharacterDimension;
use App\Models\CharacterProfile;
use App\Models\HabitItem;
use App\Models\Unit;
use App\Models\User;
use App\Services\Character\CharacterSettingsService;
use App\Services\Features\FeatureFlags;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function characterAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    return $admin;
}

/**
 * @return array<string, mixed>
 */
function dimensionPayload(array $overrides = []): array
{
    return array_replace([
        'name' => 'Kerja sama',
        'is_active' => true,
        'description_templates' => ['SAB' => 'a', 'BSH' => 'b', 'SB' => 'c', 'MB' => 'd'],
        'components' => [
            ['source' => 'habits_aspect', 'weight' => 60, 'params' => ['aspect' => 'social']],
            ['source' => 'teacher_observation', 'weight' => 40, 'params' => []],
        ],
    ], $overrides);
}

test('tamu diarahkan ke login dan peran tanpa izin pengaturan ditolak', function (): void {
    $this->get(route('admin.settings.character.edit'))->assertRedirect(route('login'));

    $teacher = User::factory()->create();
    $teacher->assignRole(RoleEnum::Teacher->value);
    $unit = Unit::factory()->create();

    $this->actingAs($teacher)->get(route('admin.settings.character.edit'))->assertForbidden();
    $this->actingAs($teacher)
        ->post(route('admin.settings.character.curriculum', $unit), ['curriculum' => 'merdeka'])
        ->assertForbidden();
});

test('unit tanpa profil tampil kosong lalu menerapkan kurikulum merdeka membuat dimensi dan skala bawaan', function (): void {
    $admin = characterAdmin();
    $unit = Unit::factory()->create(['code' => 'MI']);

    $this->actingAs($admin)->get(route('admin.settings.character.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/settings/character')
            ->where('unit.code', 'MI')
            ->where('profile', null)
            ->where('dimensions', [])
        );

    $this->actingAs($admin)
        ->post(route('admin.settings.character.curriculum', $unit), ['curriculum' => 'merdeka'])
        ->assertRedirect(route('admin.settings.character.edit', ['unit' => 'MI']));

    $profile = CharacterProfile::query()->where('unit_id', $unit->id)->firstOrFail();

    expect($profile->curriculum)->toBe(Curriculum::Merdeka)
        ->and(array_column($profile->scale, 'code'))->toBe(['SAB', 'BSH', 'SB', 'MB'])
        ->and(CharacterDimension::query()->where('unit_id', $unit->id)->where('is_active', true)->count())->toBe(3)
        ->and(AuditLog::query()->where('action', 'character.curriculum_applied')->exists())->toBeTrue();

    $dimension = CharacterDimension::query()->where('code', 'kedisiplinan')->firstOrFail();
    expect($dimension->components->sum('weight'))->toBe(100);
});

test('mengganti kurikulum menonaktifkan dimensi lama tanpa menghapusnya', function (): void {
    $admin = characterAdmin();
    $unit = Unit::factory()->create();
    $service = app(CharacterSettingsService::class);

    $service->applyCurriculum($unit, Curriculum::Merdeka, $admin);
    $keimanan = CharacterDimension::query()->where('code', 'keimanan')->firstOrFail();

    $service->applyCurriculum($unit, Curriculum::K13, $admin);

    expect(CharacterDimension::query()->whereKey($keimanan->id)->value('is_active'))->toBeFalse()
        ->and(CharacterDimension::query()->where('unit_id', $unit->id)->count())->toBe(5)
        ->and(CharacterDimension::query()->where('unit_id', $unit->id)->where('is_active', true)->pluck('code')->all())
        ->toEqualCanonicalizing(['spiritual', 'sosial'])
        ->and(CharacterProfile::query()->where('unit_id', $unit->id)->value('curriculum'))->toBe(Curriculum::K13);
});

test('dimensi dengan jumlah bobot bukan 100 ditolak', function (): void {
    $admin = characterAdmin();
    $unit = Unit::factory()->create();
    app(CharacterSettingsService::class)->applyCurriculum($unit, Curriculum::Merdeka, $admin);

    $this->actingAs($admin)
        ->post(route('admin.settings.character.dimensions.store', $unit), dimensionPayload([
            'components' => [
                ['source' => 'habits_aspect', 'weight' => 60, 'params' => ['aspect' => 'social']],
                ['source' => 'teacher_observation', 'weight' => 30, 'params' => []],
            ],
        ]))
        ->assertSessionHasErrors('components');

    expect(CharacterDimension::query()->where('name', 'Kerja sama')->exists())->toBeFalse();
});

test('menambah dan mengubah dimensi menyimpan komponen dengan parameter yang dibersihkan dan tercatat audit', function (): void {
    $admin = characterAdmin();
    $unit = Unit::factory()->create();
    app(CharacterSettingsService::class)->applyCurriculum($unit, Curriculum::Merdeka, $admin);

    $this->actingAs($admin)
        ->post(route('admin.settings.character.dimensions.store', $unit), dimensionPayload([
            'components' => [
                ['source' => 'habits_aspect', 'weight' => 60, 'params' => ['aspect' => 'social', 'item_ids' => [999]]],
                ['source' => 'violation_points', 'weight' => 40, 'params' => ['tiers' => [['max' => 0, 'score' => 100], ['max' => null, 'score' => 50]]]],
            ],
        ]))
        ->assertSessionHasNoErrors();

    $dimension = CharacterDimension::query()->where('name', 'Kerja sama')->firstOrFail();

    expect($dimension->code)->toBe('kerja-sama')
        ->and($dimension->components[0]->params)->toBe(['aspect' => 'social'])
        ->and($dimension->components[1]->params['tiers'][1])->toBe(['max' => null, 'score' => 50]);

    $this->actingAs($admin)
        ->put(route('admin.settings.character.dimensions.update', [$unit, $dimension]), dimensionPayload(['name' => 'Kerja sama tim', 'is_active' => false]))
        ->assertSessionHasNoErrors();

    expect($dimension->fresh()?->name)->toBe('Kerja sama tim')
        ->and($dimension->fresh()?->is_active)->toBeFalse()
        ->and(AuditLog::query()->where('action', 'character.dimension_updated')->exists())->toBeTrue();
});

test('dimensi unit lain menghasilkan 404', function (): void {
    $admin = characterAdmin();
    $unit = Unit::factory()->create();
    $other = Unit::factory()->create();
    $service = app(CharacterSettingsService::class);
    $service->applyCurriculum($unit, Curriculum::Merdeka, $admin);
    $service->applyCurriculum($other, Curriculum::Merdeka, $admin);
    $foreign = CharacterDimension::query()->where('unit_id', $other->id)->firstOrFail();

    $this->actingAs($admin)
        ->put(route('admin.settings.character.dimensions.update', [$unit, $foreign]), dimensionPayload())
        ->assertNotFound();
});

test('tingkatan poin dan item pembiasaan divalidasi', function (): void {
    $admin = characterAdmin();
    $unit = Unit::factory()->create();
    $other = Unit::factory()->create();
    app(CharacterSettingsService::class)->applyCurriculum($unit, Curriculum::Merdeka, $admin);
    $foreignItem = HabitItem::factory()->create(['unit_id' => $other->id]);

    $this->actingAs($admin)
        ->post(route('admin.settings.character.dimensions.store', $unit), dimensionPayload([
            'components' => [
                ['source' => 'habits_items', 'weight' => 50, 'params' => ['item_ids' => [$foreignItem->id]]],
                ['source' => 'merit_points', 'weight' => 50, 'params' => ['tiers' => [['max' => 10, 'score' => 80], ['max' => 5, 'score' => 90]]]],
            ],
        ]))
        ->assertSessionHasErrors(['components.0.params.item_ids', 'components.1.params.tiers']);
});

test('skala capaian harus menurun dan berakhir di nol', function (): void {
    $admin = characterAdmin();
    $unit = Unit::factory()->create();
    app(CharacterSettingsService::class)->applyCurriculum($unit, Curriculum::K13, $admin);

    $this->actingAs($admin)
        ->put(route('admin.settings.character.scale', $unit), [
            'scale' => [['code' => 'A', 'label' => 'Sangat Baik', 'min' => 80], ['code' => 'B', 'label' => 'Baik', 'min' => 85]],
            'coverage_min_pct' => 60,
        ])
        ->assertSessionHasErrors('scale');

    $this->actingAs($admin)
        ->put(route('admin.settings.character.scale', $unit), [
            'scale' => [['code' => 'a', 'label' => 'Sangat Baik', 'min' => 85], ['code' => 'B', 'label' => 'Baik', 'min' => 0]],
            'coverage_min_pct' => 70,
        ])
        ->assertSessionHasNoErrors();

    $profile = CharacterProfile::query()->where('unit_id', $unit->id)->firstOrFail();
    expect($profile->scale[0])->toBe(['code' => 'A', 'label' => 'Sangat Baik', 'min' => 85])
        ->and($profile->coverage_min_pct)->toBe(70);
});

test('halaman pengaturan 404 saat modul penilaian karakter dimatikan', function (): void {
    $admin = characterAdmin();
    app(FeatureFlags::class)->update([Feature::Attitude->value => false], $admin);

    $this->actingAs($admin)->get(route('admin.settings.character.edit'))->assertNotFound();
});
