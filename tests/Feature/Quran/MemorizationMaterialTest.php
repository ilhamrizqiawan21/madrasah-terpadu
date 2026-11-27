<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Role;
use App\Models\FeatureFlag;
use App\Models\MemorizationMaterial;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    FeatureFlag::query()->updateOrCreate(
        ['key' => Feature::Quran->value],
        ['enabled' => true]
    );
});

it('forbids teachers without quran.manage from managing hadith and prayer materials', function (): void {
    $guru = User::factory()->create();
    $guru->assignRole(Role::Teacher->value);

    $this->actingAs($guru)
        ->post(route('admin.quran.materials.store'), [
            'kind' => 'hadith',
            'title' => 'Hadits Niat',
        ])
        ->assertForbidden();
});

it('allows admin to create, update, toggle, and preserve utf8 arabic text for materials', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);
    $unit = Unit::factory()->create();

    $arabic = 'إِنَّمَا الأَعْمَالُ بِالنِّيَّاتِ';

    $this->actingAs($admin)
        ->post(route('admin.quran.materials.store'), [
            'unit_id' => $unit->id,
            'kind' => 'hadith',
            'title' => 'Hadits Tentang Niat',
            'arabic_text' => $arabic,
            'translation' => 'Sesungguhnya setiap amal tergantung pada niatnya.',
            'source' => 'HR. Bukhari & Muslim',
            'sort_order' => 1,
        ])
        ->assertRedirect();

    $material = MemorizationMaterial::query()->firstOrFail();
    expect($material->arabic_text)->toBe($arabic)
        ->and($material->kind)->toBe('hadith')
        ->and($material->is_active)->toBeTrue();

    $this->actingAs($admin)
        ->put(route('admin.quran.materials.update', $material), [
            'unit_id' => $unit->id,
            'kind' => 'prayer',
            'title' => 'Doa Sebelum Tidur',
            'arabic_text' => 'بِاسْمِكَ اللَّهُمَّ أَمُوتُ وَأَحْيَا',
            'translation' => 'Dengan nama-Mu ya Allah aku mati dan hidup.',
            'source' => 'HR. Bukhari',
            'sort_order' => 2,
        ])
        ->assertRedirect();

    expect($material->fresh()->kind)->toBe('prayer')
        ->and($material->fresh()->title)->toBe('Doa Sebelum Tidur');

    $this->actingAs($admin)
        ->patch(route('admin.quran.materials.toggle', $material), ['is_active' => false])
        ->assertRedirect();

    expect($material->fresh()->is_active)->toBeFalse();
});
