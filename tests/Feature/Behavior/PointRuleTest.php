<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\PointCategory;
use App\Enums\Role;
use App\Models\FeatureFlag;
use App\Models\PointRule;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    FeatureFlag::query()->updateOrCreate(
        ['key' => Feature::Behavior->value],
        ['enabled' => true]
    );
});

it('forbids unauthorized users from managing point rules', function (): void {
    $guru = User::factory()->create();
    $guru->assignRole(Role::Teacher->value);

    $this->actingAs($guru)
        ->get(route('admin.behavior.rules.index'))
        ->assertForbidden();

    $this->actingAs($guru)
        ->post(route('admin.behavior.rules.store'), [
            'code' => 'P01',
            'name' => 'Terlambat apel',
            'category' => PointCategory::Violation->value,
            'points' => 5,
        ])
        ->assertForbidden();
});

it('allows bk and admin to create, update, and deactivate point rules', function (): void {
    $bk = User::factory()->create();
    $bk->assignRole(Role::Counselor->value);
    $unit = Unit::factory()->create(['code' => 'MI']);

    $this->actingAs($bk)
        ->post(route('admin.behavior.rules.store'), [
            'unit_id' => $unit->id,
            'code' => 'p-01',
            'name' => 'Terlambat masuk madrasah',
            'category' => PointCategory::Violation->value,
            'points' => 5,
        ])
        ->assertRedirect();

    $rule = PointRule::query()->where('code', 'P-01')->firstOrFail();
    expect($rule->unit_id)->toBe($unit->id)
        ->and($rule->category)->toBe(PointCategory::Violation)
        ->and($rule->points)->toBe(5)
        ->and($rule->is_active)->toBeTrue();

    $this->actingAs($bk)
        ->put(route('admin.behavior.rules.update', $rule), [
            'unit_id' => $unit->id,
            'code' => 'P-01',
            'name' => 'Terlambat apel pagi',
            'category' => PointCategory::Achievement->value,
            'level' => 'Kabupaten',
            'points' => 15,
        ])
        ->assertRedirect();

    $rule->refresh();
    expect($rule->name)->toBe('Terlambat apel pagi')
        ->and($rule->category)->toBe(PointCategory::Achievement)
        ->and($rule->level)->toBe('Kabupaten')
        ->and($rule->points)->toBe(15);

    $this->actingAs($bk)
        ->patch(route('admin.behavior.rules.toggle', $rule), ['is_active' => false])
        ->assertRedirect();

    expect($rule->fresh()->is_active)->toBeFalse();
});

it('enforces unique code per unit and rejects non-positive points', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);
    $unitA = Unit::factory()->create(['code' => 'MI']);
    $unitB = Unit::factory()->create(['code' => 'RA']);

    $this->actingAs($admin)
        ->post(route('admin.behavior.rules.store'), [
            'unit_id' => $unitA->id,
            'code' => 'K-01',
            'name' => 'Sholat berjamaah',
            'category' => PointCategory::GoodDeed->value,
            'points' => 0,
        ])
        ->assertSessionHasErrors('points');

    PointRule::factory()->create([
        'unit_id' => $unitA->id,
        'code' => 'K-01',
    ]);

    // Same code in same unit is rejected
    $this->actingAs($admin)
        ->post(route('admin.behavior.rules.store'), [
            'unit_id' => $unitA->id,
            'code' => 'k-01',
            'name' => 'Duplikat',
            'category' => PointCategory::GoodDeed->value,
            'points' => 5,
        ])
        ->assertSessionHasErrors('code');

    // Same code in different unit is allowed
    $this->actingAs($admin)
        ->post(route('admin.behavior.rules.store'), [
            'unit_id' => $unitB->id,
            'code' => 'K-01',
            'name' => 'Sholat berjamaah RA',
            'category' => PointCategory::GoodDeed->value,
            'points' => 5,
        ])
        ->assertSessionHasNoErrors();

    // Global rule (unit_id null) also enforces unique code
    PointRule::factory()->create(['unit_id' => null, 'code' => 'G-01']);

    $this->actingAs($admin)
        ->post(route('admin.behavior.rules.store'), [
            'unit_id' => null,
            'code' => 'G-01',
            'name' => 'Duplikat global',
            'category' => PointCategory::Violation->value,
            'points' => 3,
        ])
        ->assertSessionHasErrors('code');
});

it('imports point rules from xlsx file', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);
    $unit = Unit::factory()->create(['code' => 'MI']);

    $tmpPath = sys_get_temp_dir().'/point-rules-test-'.uniqid().'.xlsx';
    $writer = new Writer;
    $writer->openToFile($tmpPath);
    $writer->addRow(Row::fromValues(['kode', 'nama', 'kategori', 'level', 'poin']));
    $writer->addRow(Row::fromValues(['P-101', 'Tidak membawa alat sholat', 'pelanggaran', '', 5]));
    $writer->addRow(Row::fromValues(['PR-101', 'Juara 1 MTQ', 'prestasi', 'Kabupaten', 25]));
    $writer->addRow(Row::fromValues(['KB-101', 'Membantu piket kelas', 'kebaikan', '', 3]));
    $writer->close();

    $file = new UploadedFile($tmpPath, 'tata-tertib.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $this->actingAs($admin)
        ->post(route('admin.behavior.rules.import'), [
            'unit_id' => $unit->id,
            'file' => $file,
        ])
        ->assertRedirect();

    expect(PointRule::query()->where('unit_id', $unit->id)->count())->toBe(3)
        ->and(PointRule::query()->where('code', 'PR-101')->firstOrFail()->level)->toBe('Kabupaten');

    @unlink($tmpPath);
});
