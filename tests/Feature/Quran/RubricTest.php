<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Role;
use App\Models\FeatureFlag;
use App\Models\RubricVersion;
use App\Models\User;
use App\Services\Quran\RubricService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\RubricSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RubricSeeder::class);

    FeatureFlag::query()->updateOrCreate(
        ['key' => Feature::Quran->value],
        ['enabled' => true]
    );
});

it('seeds default rubric as draft and locks published rubric versions from further edits', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);

    $version = RubricVersion::query()->with('criteria')->firstOrFail();
    expect($version->published_at)->toBeNull()
        ->and($version->criteria)->toHaveCount(3);

    $this->actingAs($admin)
        ->post(route('admin.quran.rubrics.publish', $version))
        ->assertRedirect();

    $version->refresh();
    expect($version->published_at)->not->toBeNull();

    // Editing a published version must be rejected
    expect(fn () => app(RubricService::class)->updateDraftCriteria($admin, $version, [
        ['name' => 'Diubah', 'mode' => 'direct', 'weight' => 100, 'max_score' => 100, 'deduction_per_error' => 0, 'pass_threshold' => 75],
    ]))->toThrow(ValidationException::class);
});
