<?php

declare(strict_types=1);

use App\Enums\BillingTargetType;
use App\Enums\DiscountKind;
use App\Enums\Feature;
use App\Enums\InvoiceAdjustmentType;
use App\Enums\InvoiceStatus;
use App\Enums\Role as RoleEnum;
use App\Enums\Semester;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\FeeType;
use App\Models\Invoice;
use App\Models\InvoiceAdjustment;
use App\Models\Student;
use App\Models\StudentDiscount;
use App\Models\Unit;
use App\Models\User;
use App\Services\Billing\BillingRunService;
use App\Services\Features\FeatureFlags;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function enableBillingForDiscounts(User $actor): void
{
    app(FeatureFlags::class)->update([
        Feature::Billing->value => true,
    ], $actor);
}

test('bendahara dapat mengelola master diskon dan beasiswa berulang dengan audit', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    enableBillingForDiscounts($admin);

    $treasurer = User::factory()->create();
    $treasurer->assignRole(RoleEnum::Treasurer->value);

    $unitHead = User::factory()->create();
    $unitHead->assignRole(RoleEnum::UnitHead->value);

    $this->actingAs($unitHead)
        ->get(route('admin.discounts.index'))
        ->assertForbidden();

    $mi = Unit::factory()->create(['code' => 'MI']);
    $student = Student::factory()->create(['unit_id' => $mi->id, 'name' => 'Farhan']);
    $spp = FeeType::factory()->create(['unit_id' => $mi->id, 'name' => 'SPP MI', 'default_amount' => 150000]);

    $this->actingAs($treasurer)
        ->get(route('admin.discounts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/billing/discounts'));

    $this->actingAs($treasurer)
        ->post(route('admin.discounts.store'), [
            'student_id' => $student->id,
            'fee_type_id' => $spp->id,
            'kind' => DiscountKind::Percent->value,
            'value' => 50,
            'valid_from' => '2026-07-01',
            'valid_until' => '2026-08-31',
            'reason' => 'Beasiswa tahfidz',
        ])
        ->assertRedirect();

    $discount = StudentDiscount::query()->firstOrFail();
    expect($discount->kind)->toBe(DiscountKind::Percent)
        ->and($discount->value)->toBe(50)
        ->and($discount->is_active)->toBeTrue();

    expect(AuditLog::query()->where('action', 'student_discount.created')->where('subject_id', $discount->id)->exists())->toBeTrue();

    // Persen > 100 ditolak
    $this->actingAs($treasurer)
        ->post(route('admin.discounts.store'), [
            'student_id' => $student->id,
            'fee_type_id' => $spp->id,
            'kind' => DiscountKind::Percent->value,
            'value' => 120,
            'valid_from' => '2026-07-01',
            'reason' => 'Tidak valid',
        ])
        ->assertSessionHasErrors('value');
});

test('potongan berulang hanya berlaku dalam masa berlaku, tercatat sebagai penyesuaian, dan tagihan tidak negatif', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    enableBillingForDiscounts($admin);

    $mi = Unit::factory()->create(['code' => 'MI']);
    AcademicYear::factory()->active()->create([
        'name' => '2026/2027',
        'semester' => Semester::Ganjil,
        'starts_on' => '2026-07-13',
        'ends_on' => '2026-12-20',
    ]);

    $s1 = Student::factory()->create(['unit_id' => $mi->id, 'status' => StudentStatus::Active]);
    $s2 = Student::factory()->create(['unit_id' => $mi->id, 'status' => StudentStatus::Active]);
    $spp = FeeType::factory()->create(['unit_id' => $mi->id, 'recurrence' => 'monthly', 'default_amount' => 150000]);

    // s1: diskon 50% hanya Juli–Agustus 2026
    StudentDiscount::query()->create([
        'student_id' => $s1->id,
        'fee_type_id' => $spp->id,
        'kind' => DiscountKind::Percent,
        'value' => 50,
        'valid_from' => '2026-07-01',
        'valid_until' => '2026-08-31',
        'reason' => 'Beasiswa 50%',
        'is_active' => true,
        'created_by' => $admin->id,
    ]);

    // s2: diskon nominal 200.000 melebihi tagihan 150.000 -> dibatasi 150.000 (tidak negatif, langsung lunas)
    StudentDiscount::query()->create([
        'student_id' => $s2->id,
        'fee_type_id' => $spp->id,
        'kind' => DiscountKind::Fixed,
        'value' => 200000,
        'valid_from' => '2026-07-01',
        'valid_until' => null,
        'reason' => 'Pembebasan penuh yatim',
        'is_active' => true,
        'created_by' => $admin->id,
    ]);

    app(BillingRunService::class)->execute($admin, [
        'fee_type_id' => $spp->id,
        'target_type' => BillingTargetType::Students->value,
        'student_ids' => [$s1->id, $s2->id],
        'period_from' => '2026-07',
        'period_to' => '2026-09',
        'due_mode' => 'day',
        'due_day' => 10,
        'default_amount' => 150000,
    ]);

    $s1July = Invoice::query()->where('student_id', $s1->id)->whereDate('period', '2026-07-01')->firstOrFail();
    $s1Sept = Invoice::query()->where('student_id', $s1->id)->whereDate('period', '2026-09-01')->firstOrFail();
    $s2July = Invoice::query()->where('student_id', $s2->id)->whereDate('period', '2026-07-01')->firstOrFail();

    expect($s1July->amount)->toBe(150000)
        ->and($s1July->adjustment_total)->toBe(75000)
        ->and($s1July->remaining())->toBe(75000)
        ->and($s1July->status)->toBe(InvoiceStatus::Unpaid);

    // September di luar masa berlaku -> tidak ada potongan
    expect($s1Sept->adjustment_total)->toBe(0)
        ->and($s1Sept->remaining())->toBe(150000);

    // s2 dibatasi agar sisa = 0 (tidak negatif) dan tercatat di invoice_adjustments
    expect($s2July->amount)->toBe(150000)
        ->and($s2July->adjustment_total)->toBe(150000)
        ->and($s2July->remaining())->toBe(0)
        ->and($s2July->status)->toBe(InvoiceStatus::Paid);

    expect(InvoiceAdjustment::query()->where('invoice_id', $s1July->id)->where('type', InvoiceAdjustmentType::Discount->value)->exists())->toBeTrue();
});
