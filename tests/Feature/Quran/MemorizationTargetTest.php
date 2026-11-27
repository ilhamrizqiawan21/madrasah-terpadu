<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\FeatureFlag;
use App\Models\MemorizationMaterial;
use App\Models\MemorizationTarget;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SurahSeeder;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SurahSeeder::class);

    FeatureFlag::query()->updateOrCreate(
        ['key' => Feature::Quran->value],
        ['enabled' => true]
    );
});

it('validates quran ayah ranges against surah ayah counts and rejects out-of-bounds ranges', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);
    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->create(['is_active' => true]);

    // An-Nas (114) has 6 ayahs -> ayah_to 10 must be rejected
    $this->actingAs($admin)
        ->post(route('admin.quran.targets.store'), [
            'unit_id' => $unit->id,
            'grade' => '1',
            'academic_year_id' => $year->id,
            'kind' => 'quran',
            'title' => 'Target Juz 30',
            'ranges' => [
                ['surah' => 114, 'ayah_from' => 1, 'ayah_to' => 10],
            ],
        ])
        ->assertSessionHasErrors('ranges');

    // Valid range: An-Nas 1..6 & Al-Ikhlas (112) 1..4
    $this->actingAs($admin)
        ->post(route('admin.quran.targets.store'), [
            'unit_id' => $unit->id,
            'grade' => '1',
            'academic_year_id' => $year->id,
            'kind' => 'quran',
            'title' => 'Target Juz 30',
            'ranges' => [
                ['surah' => 114, 'ayah_from' => 1, 'ayah_to' => 6],
                ['surah' => 112, 'ayah_from' => 1, 'ayah_to' => 4],
            ],
        ])
        ->assertRedirect();

    $target = MemorizationTarget::query()->firstOrFail();
    expect($target->kind)->toBe('quran')
        ->and($target->ranges)->toHaveCount(2);
});

it('creates hadith and prayer targets using material_ids', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);
    $unit = Unit::factory()->create();
    $year = AcademicYear::factory()->create(['is_active' => true]);

    $hadith = MemorizationMaterial::factory()->create([
        'kind' => 'hadith',
        'title' => 'Hadits Niat',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.quran.targets.store'), [
            'unit_id' => $unit->id,
            'grade' => '2',
            'academic_year_id' => $year->id,
            'kind' => 'hadith',
            'title' => 'Target Hadits Kelas 2',
            'material_ids' => [$hadith->id],
        ])
        ->assertRedirect();

    $target = MemorizationTarget::query()->where('kind', 'hadith')->firstOrFail();
    expect($target->material_ids)->toBe([$hadith->id]);
});
