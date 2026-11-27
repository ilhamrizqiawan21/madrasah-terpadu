<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\FeeRecurrence;
use App\Enums\Role as RoleEnum;
use App\Models\AuditLog;
use App\Models\FeeType;
use App\Models\Invoice;
use App\Models\Unit;
use App\Models\User;
use App\Services\Features\FeatureFlags;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function enableBillingFeature(User $actor): void
{
    app(FeatureFlags::class)->update([
        Feature::Billing->value => true,
    ], $actor);
}

test('tamu ditolak dan rute jenis tagihan 404 saat kunci fitur billing nonaktif', function (): void {
    $this->get('/admin/keuangan/jenis-tagihan')
        ->assertRedirect('/login');

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    $this->actingAs($admin)
        ->get('/admin/keuangan/jenis-tagihan')
        ->assertNotFound();
});

test('pengguna tanpa izin jenis tagihan ditolak 403 saat fitur aktif', function (RoleEnum $role): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    enableBillingFeature($admin);

    $user = User::factory()->create();
    $user->assignRole($role->value);

    $this->actingAs($user)
        ->get('/admin/keuangan/jenis-tagihan')
        ->assertForbidden();
})->with([
    RoleEnum::Teacher,
    RoleEnum::Counselor,
    RoleEnum::UnitHead,
]);

test('admin dan bendahara dapat melihat daftar jenis tagihan dengan filter dan paginasi', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    enableBillingFeature($admin);

    $treasurer = User::factory()->create();
    $treasurer->assignRole(RoleEnum::Treasurer->value);

    $mi = Unit::factory()->create(['code' => 'MI', 'name' => 'MI Darul Istiqamah']);
    $ra = Unit::factory()->create(['code' => 'RA', 'name' => 'RA Darul Istiqamah']);

    FeeType::factory()->create([
        'unit_id' => $mi->id,
        'name' => 'SPP Bulanan MI',
        'category' => 'SPP',
        'recurrence' => FeeRecurrence::Monthly,
        'default_amount' => 150000,
        'is_active' => true,
    ]);

    FeeType::factory()->create([
        'unit_id' => $ra->id,
        'name' => 'Uang Pangkal RA',
        'category' => 'Uang Pangkal',
        'recurrence' => FeeRecurrence::Once,
        'default_amount' => 750000,
        'is_active' => false,
    ]);

    $this->actingAs($treasurer)
        ->get(route('admin.fee-types.index', ['unit_id' => $mi->id, 'status' => 'active']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/billing/fee-types')
            ->has('feeTypes.data', 1)
            ->where('feeTypes.data.0.name', 'SPP Bulanan MI')
            ->where('feeTypes.data.0.default_amount', 150000)
            ->where('canManage', true));
});

test('bendahara dapat menambah, mengubah, dan menonaktifkan jenis tagihan dengan audit', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    enableBillingFeature($admin);

    $treasurer = User::factory()->create();
    $treasurer->assignRole(RoleEnum::Treasurer->value);

    $mi = Unit::factory()->create(['code' => 'MI', 'name' => 'MI Darul Istiqamah']);

    $this->actingAs($treasurer)
        ->post(route('admin.fee-types.store'), [
            'unit_id' => $mi->id,
            'name' => 'SPP MI',
            'category' => 'SPP',
            'recurrence' => FeeRecurrence::Monthly->value,
            'default_amount' => 175000,
        ])
        ->assertRedirect();

    $feeType = FeeType::query()->where('name', 'SPP MI')->firstOrFail();

    expect($feeType->default_amount)->toBe(175000)
        ->and($feeType->recurrence)->toBe(FeeRecurrence::Monthly)
        ->and($feeType->is_active)->toBeTrue();

    expect(AuditLog::query()->where('action', 'fee_type.created')->where('subject_id', $feeType->id)->exists())->toBeTrue();

    $this->actingAs($treasurer)
        ->put(route('admin.fee-types.update', $feeType), [
            'unit_id' => $mi->id,
            'name' => 'SPP MI 2026',
            'category' => 'SPP',
            'recurrence' => FeeRecurrence::Monthly->value,
            'default_amount' => 200000,
        ])
        ->assertRedirect();

    expect($feeType->fresh()->name)->toBe('SPP MI 2026')
        ->and($feeType->fresh()->default_amount)->toBe(200000);

    $this->actingAs($treasurer)
        ->patch(route('admin.fee-types.toggle', $feeType), [
            'is_active' => false,
        ])
        ->assertRedirect();

    expect($feeType->fresh()->is_active)->toBeFalse();
    expect(AuditLog::query()->where('action', 'fee_type.deactivated')->where('subject_id', $feeType->id)->exists())->toBeTrue();
});

test('validasi gagal menolak nominal negatif, sifat tidak valid, dan nama ganda dalam unit yang sama', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    enableBillingFeature($admin);

    $mi = Unit::factory()->create(['code' => 'MI']);
    FeeType::factory()->create(['unit_id' => $mi->id, 'name' => 'SPP MI']);

    $this->actingAs($admin)
        ->post(route('admin.fee-types.store'), [
            'unit_id' => $mi->id,
            'name' => 'SPP MI',
            'category' => str_repeat('A', 35),
            'recurrence' => 'weekly',
            'default_amount' => -5000,
        ])
        ->assertSessionHasErrors(['name', 'category', 'recurrence', 'default_amount']);
});

test('jenis tagihan yang belum dipakai dapat dihapus sedangkan yang sudah ada tagihan ditolak', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    enableBillingFeature($admin);

    $unused = FeeType::factory()->create(['name' => 'Kegiatan Ekstra']);
    $used = FeeType::factory()->create(['name' => 'SPP Dipakai']);

    $this->actingAs($admin)
        ->delete(route('admin.fee-types.destroy', $unused))
        ->assertRedirect();

    expect(FeeType::query()->find($unused->id))->toBeNull();
    expect(AuditLog::query()->where('action', 'fee_type.deleted')->where('subject_id', $unused->id)->exists())->toBeTrue();

    Invoice::factory()->create(['fee_type_id' => $used->id]);

    $this->actingAs($admin)
        ->delete(route('admin.fee-types.destroy', $used))
        ->assertSessionHasErrors('fee_type');

    expect(FeeType::query()->find($used->id))->not->toBeNull();
});
