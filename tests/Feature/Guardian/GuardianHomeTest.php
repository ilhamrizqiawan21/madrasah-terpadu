<?php

declare(strict_types=1);

use App\Enums\GuardianRelation;
use App\Enums\Role as RoleEnum;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Guardian\GuardianHomeSummaryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('beranda wali hanya memuat data anak sendiri, menyembunyikan kartu tanpa data, dan memakai cache 60 detik per anak', function (): void {
    $unit = Unit::factory()->create(['code' => 'MI']);
    $ownChild = Student::factory()->for($unit)->create(['name' => 'Anak Sendiri']);
    Student::factory()->for($unit)->create(['name' => 'Anak Lain']);

    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(RoleEnum::Guardian->value);
    $guardian = Guardian::query()->create([
        'user_id' => $user->id,
        'name' => $user->name,
        'phone' => '081400000001',
        'relation' => GuardianRelation::Father,
    ]);
    $guardian->students()->attach($ownChild->id, ['relation' => GuardianRelation::Father->value, 'is_primary' => true]);

    $this->actingAs($user)
        ->get(route('guardian.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('guardian/home')
            ->where('home.student.id', $ownChild->id)
            ->where('home.student.name', 'Anak Sendiri')
            ->where('home.cards', [])
        );

    $key = GuardianHomeSummaryService::cacheKey($ownChild->id);
    expect(Cache::has($key))->toBeTrue();

    app(GuardianHomeSummaryService::class)->invalidate($ownChild->id);
    expect(Cache::has($key))->toBeFalse();
});
