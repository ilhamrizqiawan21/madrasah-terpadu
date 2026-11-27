<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\InvoiceAdjustmentType;
use App\Enums\InvoiceStatus;
use App\Enums\Role as RoleEnum;
use App\Models\AuditLog;
use App\Models\FeeType;
use App\Models\Invoice;
use App\Models\InvoiceAdjustment;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Features\FeatureFlags;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function enableBillingForAdjustment(User $actor): void
{
    app(FeatureFlags::class)->update([
        Feature::Billing->value => true,
    ], $actor);
}

test('hanya pengguna berizin penyesuaian yang dapat menyesuaikan atau membatalkan tagihan', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    enableBillingForAdjustment($admin);

    $unitHead = User::factory()->create();
    $unitHead->assignRole(RoleEnum::UnitHead->value);

    $invoice = Invoice::factory()->create(['amount' => 150000]);

    $this->actingAs($unitHead)
        ->post(route('admin.invoices.adjust', $invoice), [
            'type' => InvoiceAdjustmentType::Discount->value,
            'amount' => 50000,
            'reason' => 'Beasiswa',
        ])
        ->assertForbidden();

    $this->actingAs($unitHead)
        ->post(route('admin.invoices.cancel', $invoice), [
            'reason' => 'Salah terbit',
        ])
        ->assertForbidden();
});

test('penyesuaian append-only menghitung ulang status dan menolak nominal melebihi sisa', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    enableBillingForAdjustment($admin);

    $treasurer = User::factory()->create();
    $treasurer->assignRole(RoleEnum::Treasurer->value);

    $mi = Unit::factory()->create(['code' => 'MI']);
    $student = Student::factory()->create(['unit_id' => $mi->id]);
    $feeType = FeeType::factory()->create(['unit_id' => $mi->id]);

    $invoice = Invoice::factory()->create([
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'amount' => 200000,
        'adjustment_total' => 0,
        'paid_total' => 50000,
        'status' => InvoiceStatus::Partial,
    ]);

    // Melebihi sisa (150.000) ditolak
    $this->actingAs($treasurer)
        ->post(route('admin.invoices.adjust', $invoice), [
            'type' => InvoiceAdjustmentType::Discount->value,
            'amount' => 150001,
            'reason' => 'Terlalu besar',
        ])
        ->assertSessionHasErrors('amount');

    expect(InvoiceAdjustment::query()->count())->toBe(0);

    // Penyesuaian pas sisa (150.000) membuat tagihan lunas
    $this->actingAs($treasurer)
        ->post(route('admin.invoices.adjust', $invoice), [
            'type' => InvoiceAdjustmentType::Waiver->value,
            'amount' => 150000,
            'reason' => 'Keringanan yatim',
        ])
        ->assertRedirect();

    $invoice->refresh();
    expect($invoice->adjustment_total)->toBe(150000)
        ->and($invoice->remaining())->toBe(0)
        ->and($invoice->status)->toBe(InvoiceStatus::Paid);

    expect(InvoiceAdjustment::query()->where('invoice_id', $invoice->id)->count())->toBe(1);
    expect(AuditLog::query()->where('action', 'invoice.adjusted')->where('subject_id', $invoice->id)->exists())->toBeTrue();
});

test('tagihan tanpa pembayaran dapat dibatalkan sedangkan tagihan berpembayaran ditolak', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    enableBillingForAdjustment($admin);

    $paidInvoice = Invoice::factory()->create([
        'amount' => 200000,
        'paid_total' => 50000,
        'status' => InvoiceStatus::Partial,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.invoices.cancel', $paidInvoice), [
            'reason' => 'Salah terbit',
        ])
        ->assertSessionHasErrors('invoice');

    expect($paidInvoice->fresh()->status)->toBe(InvoiceStatus::Partial);

    $unpaidInvoice = Invoice::factory()->create([
        'amount' => 200000,
        'paid_total' => 0,
        'status' => InvoiceStatus::Unpaid,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.invoices.cancel', $unpaidInvoice), [
            'reason' => 'Siswa mutasi sebelum awal bulan',
        ])
        ->assertRedirect();

    $unpaidInvoice->refresh();
    expect($unpaidInvoice->status)->toBe(InvoiceStatus::Cancelled)
        ->and($unpaidInvoice->cancelled_reason)->toBe('Siswa mutasi sebelum awal bulan');

    expect(AuditLog::query()->where('action', 'invoice.cancelled')->where('subject_id', $unpaidInvoice->id)->exists())->toBeTrue();
});
