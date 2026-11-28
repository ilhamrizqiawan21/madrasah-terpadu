<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role as RoleEnum;
use App\Models\AuditLog;
use App\Models\FeeType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Billing\ManualPaymentService;
use App\Services\Features\FeatureFlags;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function enableBillingForReversal(User $actor): void
{
    app(FeatureFlags::class)->update([
        Feature::Billing->value => true,
    ], $actor);
}

test('hanya bendahara atau admin yang dapat membalik pembayaran', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    enableBillingForReversal($admin);

    $unitHead = User::factory()->create();
    $unitHead->assignRole(RoleEnum::UnitHead->value);

    $mi = Unit::factory()->create(['code' => 'MI']);
    $student = Student::factory()->create(['unit_id' => $mi->id]);
    $invoice = Invoice::factory()->create(['student_id' => $student->id, 'amount' => 100000]);

    $payment = app(ManualPaymentService::class)->receive($admin, $student, PaymentMethod::Cash, [
        ['invoice_id' => $invoice->id, 'amount' => 100000],
    ]);

    $this->actingAs($unitHead)
        ->post(route('admin.payments.reverse', $payment), [
            'reason' => 'Salah input',
        ])
        ->assertForbidden();
});

test('pembalikan pembayaran mengembalikan status tagihan ke unpaid atau partial dan mencatat audit', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    enableBillingForReversal($admin);

    $treasurer = User::factory()->create();
    $treasurer->assignRole(RoleEnum::Treasurer->value);

    $mi = Unit::factory()->create(['code' => 'MI']);
    $student = Student::factory()->create(['unit_id' => $mi->id]);
    $f1 = FeeType::factory()->create(['unit_id' => $mi->id]);
    $f2 = FeeType::factory()->create(['unit_id' => $mi->id]);

    $inv1 = Invoice::factory()->create(['student_id' => $student->id, 'fee_type_id' => $f1->id, 'amount' => 150000]);
    $inv2 = Invoice::factory()->create(['student_id' => $student->id, 'fee_type_id' => $f2->id, 'amount' => 200000]);

    $service = app(ManualPaymentService::class);
    // Pembayaran 1: cicilan 50.000 ke inv2
    $service->receive($treasurer, $student, PaymentMethod::Cash, [
        ['invoice_id' => $inv2->id, 'amount' => 50000],
    ]);

    // Pembayaran 2: lunasi inv1 (150.000) dan tambah 150.000 ke inv2
    $payment2 = $service->receive($treasurer, $student, PaymentMethod::Transfer, [
        ['invoice_id' => $inv1->id, 'amount' => 150000],
        ['invoice_id' => $inv2->id, 'amount' => 150000],
    ], 'TRF-999');

    expect($inv1->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($inv2->fresh()->status)->toBe(InvoiceStatus::Paid);

    $this->actingAs($treasurer)
        ->post(route('admin.payments.reverse', $payment2), [
            'reason' => 'Transfer gagal kliring',
        ])
        ->assertRedirect();

    $payment2->refresh();
    expect($payment2->reversed_at)->not->toBeNull()
        ->and($payment2->reversal_reason)->toBe('Transfer gagal kliring')
        ->and($payment2->reversed_by)->toBe($treasurer->id);

    // inv1 kembali unpaid, inv2 kembali partial (karena masih ada pembayaran 1)
    expect($inv1->fresh()->paid_total)->toBe(0)
        ->and($inv1->fresh()->status)->toBe(InvoiceStatus::Unpaid)
        ->and($inv2->fresh()->paid_total)->toBe(50000)
        ->and($inv2->fresh()->status)->toBe(InvoiceStatus::Partial);

    expect(AuditLog::query()->where('action', 'payment.reversed')->where('subject_id', $payment2->id)->exists())->toBeTrue();

    // Pembalikan kedua pada pembayaran yang sama ditolak
    $this->actingAs($treasurer)
        ->post(route('admin.payments.reverse', $payment2), [
            'reason' => 'Ulang',
        ])
        ->assertSessionHasErrors('payment');
});

test('pembayaran midtrans tidak dapat dibalik dari kasir', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    enableBillingForReversal($admin);

    $mi = Unit::factory()->create(['code' => 'MI']);
    $student = Student::factory()->create(['unit_id' => $mi->id]);
    $invoice = Invoice::factory()->create(['student_id' => $student->id, 'amount' => 150000, 'paid_total' => 150000, 'status' => InvoiceStatus::Paid]);

    $midtransPayment = Payment::query()->create([
        'student_id' => $student->id,
        'method' => PaymentMethod::Midtrans,
        'amount' => 150000,
        'paid_at' => now(),
        'receipt_number' => 'KW-2026-000999',
        'created_at' => now(),
    ]);
    PaymentAllocation::query()->create([
        'payment_id' => $midtransPayment->id,
        'invoice_id' => $invoice->id,
        'amount' => 150000,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.payments.reverse', $midtransPayment), [
            'reason' => 'Minta refund',
        ])
        ->assertSessionHasErrors('payment');

    expect($midtransPayment->fresh()->reversed_at)->toBeNull()
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});
