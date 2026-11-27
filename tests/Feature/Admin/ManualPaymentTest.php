<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role as RoleEnum;
use App\Models\AuditLog;
use App\Models\FeeType;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\InvoicePaidNotification;
use App\Services\Billing\ManualPaymentService;
use App\Services\Features\FeatureFlags;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function enableBillingForCashier(User $actor): void
{
    app(FeatureFlags::class)->update([
        Feature::Billing->value => true,
    ], $actor);
}

test('hanya bendahara atau admin yang dapat membuka kasir dan menerima pembayaran', function (): void {
    $this->get('/admin/keuangan/kasir')
        ->assertRedirect('/login');

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    enableBillingForCashier($admin);

    $unitHead = User::factory()->create();
    $unitHead->assignRole(RoleEnum::UnitHead->value);

    $this->actingAs($unitHead)
        ->get('/admin/keuangan/kasir')
        ->assertForbidden();

    $treasurer = User::factory()->create();
    $treasurer->assignRole(RoleEnum::Treasurer->value);

    $this->actingAs($treasurer)
        ->get('/admin/keuangan/kasir')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/billing/cashier'));
});

test('kasir dapat menerima pembayaran cicilan dan penuh, memperbarui status tagihan, mengirim notifikasi wali, dan mencatat audit', function (): void {
    Notification::fake();
    $this->travelTo(now('Asia/Makassar')->setDate(2026, 11, 28)->setTime(9, 0, 0));

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    enableBillingForCashier($admin);

    $treasurer = User::factory()->create();
    $treasurer->assignRole(RoleEnum::Treasurer->value);

    $mi = Unit::factory()->create(['code' => 'MI']);
    $student = Student::factory()->create(['unit_id' => $mi->id, 'name' => 'Aisyah']);
    $guardianUser = User::factory()->create();
    $guardianUser->assignRole(RoleEnum::Guardian->value);
    $guardian = Guardian::factory()->create(['user_id' => $guardianUser->id]);
    $guardian->students()->attach($student->id, ['relation' => 'ibu', 'is_primary' => true]);

    $f1 = FeeType::factory()->create(['unit_id' => $mi->id]);
    $f2 = FeeType::factory()->create(['unit_id' => $mi->id]);

    $inv1 = Invoice::factory()->create([
        'student_id' => $student->id,
        'fee_type_id' => $f1->id,
        'amount' => 200000,
        'status' => InvoiceStatus::Unpaid,
    ]);
    $inv2 = Invoice::factory()->create([
        'student_id' => $student->id,
        'fee_type_id' => $f2->id,
        'amount' => 150000,
        'status' => InvoiceStatus::Unpaid,
    ]);

    $this->actingAs($treasurer)
        ->post(route('admin.cashier.store'), [
            'student_id' => $student->id,
            'method' => PaymentMethod::Cash->value,
            'allocations' => [
                ['invoice_id' => $inv1->id, 'amount' => 100000],
                ['invoice_id' => $inv2->id, 'amount' => 150000],
            ],
        ])
        ->assertRedirect();

    $payment = Payment::query()->firstOrFail();

    expect($payment->amount)->toBe(250000)
        ->and($payment->receipt_number)->toBe('KW-2026-000001')
        ->and($payment->allocations)->toHaveCount(2);

    expect($inv1->fresh()->paid_total)->toBe(100000)
        ->and($inv1->fresh()->status)->toBe(InvoiceStatus::Partial);

    expect($inv2->fresh()->paid_total)->toBe(150000)
        ->and($inv2->fresh()->status)->toBe(InvoiceStatus::Paid);

    expect(AuditLog::query()->where('action', 'payment.received')->where('subject_id', $payment->id)->exists())->toBeTrue();

    Notification::assertSentTo($guardianUser, InvoicePaidNotification::class);
});

test('alokasi melebihi sisa tagihan atau tagihan milik siswa lain ditolak', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    enableBillingForCashier($admin);

    $mi = Unit::factory()->create(['code' => 'MI']);
    $s1 = Student::factory()->create(['unit_id' => $mi->id]);
    $s2 = Student::factory()->create(['unit_id' => $mi->id]);

    $inv1 = Invoice::factory()->create(['student_id' => $s1->id, 'amount' => 100000]);
    $inv2 = Invoice::factory()->create(['student_id' => $s2->id, 'amount' => 100000]);

    $this->actingAs($admin)
        ->post(route('admin.cashier.store'), [
            'student_id' => $s1->id,
            'method' => PaymentMethod::Transfer->value,
            'reference' => 'TRF-001',
            'allocations' => [
                ['invoice_id' => $inv1->id, 'amount' => 100001],
            ],
        ])
        ->assertSessionHasErrors('allocations');

    $this->actingAs($admin)
        ->post(route('admin.cashier.store'), [
            'student_id' => $s1->id,
            'method' => PaymentMethod::Cash->value,
            'allocations' => [
                ['invoice_id' => $inv2->id, 'amount' => 50000],
            ],
        ])
        ->assertSessionHasErrors('allocations');

    expect(Payment::query()->count())->toBe(0);
});

test('dua pembayaran berurutan menghasilkan nomor kuitansi unik berurutan di MariaDB', function (): void {
    $this->travelTo(now('Asia/Makassar')->setDate(2026, 11, 28)->setTime(10, 0, 0));

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    $mi = Unit::factory()->create(['code' => 'MI']);
    $student = Student::factory()->create(['unit_id' => $mi->id]);
    $f1 = FeeType::factory()->create(['unit_id' => $mi->id]);
    $f2 = FeeType::factory()->create(['unit_id' => $mi->id]);

    $inv1 = Invoice::factory()->create(['student_id' => $student->id, 'fee_type_id' => $f1->id, 'amount' => 100000]);
    $inv2 = Invoice::factory()->create(['student_id' => $student->id, 'fee_type_id' => $f2->id, 'amount' => 100000]);

    $service = app(ManualPaymentService::class);

    $p1 = $service->receive($admin, $student, PaymentMethod::Cash, [
        ['invoice_id' => $inv1->id, 'amount' => 100000],
    ]);
    $p2 = $service->receive($admin, $student, PaymentMethod::Transfer, [
        ['invoice_id' => $inv2->id, 'amount' => 100000],
    ], 'TRF-002');

    expect($p1->receipt_number)->toBe('KW-2026-000001')
        ->and($p2->receipt_number)->toBe('KW-2026-000002');
})->group('mysql');
