<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\PaymentMethod;
use App\Enums\Role as RoleEnum;
use App\Models\FeeType;
use App\Models\Guardian;
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

function enableBillingForReceipt(User $actor): void
{
    app(FeatureFlags::class)->update([
        Feature::Billing->value => true,
    ], $actor);
}

test('staf berizin dan wali pemilik anak dapat mengunduh kuitansi PDF', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    enableBillingForReceipt($admin);

    $treasurer = User::factory()->create(['name' => 'Bendahara Uji']);
    $treasurer->assignRole(RoleEnum::Treasurer->value);

    $mi = Unit::factory()->create(['code' => 'MI', 'name' => 'MI Darul Istiqamah']);
    $student = Student::factory()->create(['unit_id' => $mi->id, 'name' => 'Nadia']);

    $guardianUser = User::factory()->create();
    $guardianUser->assignRole(RoleEnum::Guardian->value);
    $guardian = Guardian::factory()->create(['user_id' => $guardianUser->id]);
    $guardian->students()->attach($student->id, ['relation' => 'ibu', 'is_primary' => true]);

    $feeType = FeeType::factory()->create(['unit_id' => $mi->id, 'name' => 'SPP MI']);
    $invoice = Invoice::factory()->create([
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'description' => 'SPP MI — November 2026',
        'amount' => 150000,
    ]);

    $payment = app(ManualPaymentService::class)->receive($treasurer, $student, PaymentMethod::Cash, [
        ['invoice_id' => $invoice->id, 'amount' => 150000],
    ]);

    $staffRes = $this->actingAs($treasurer)
        ->get(route('admin.payments.receipt', $payment))
        ->assertOk();

    expect($staffRes->headers->get('content-type'))->toContain('application/pdf')
        ->and($staffRes->getContent())->toStartWith('%PDF');

    $guardianRes = $this->actingAs($guardianUser)
        ->get(route('guardian.students.payments.receipt', [$student, $payment]))
        ->assertOk();

    expect($guardianRes->headers->get('content-type'))->toContain('application/pdf')
        ->and($guardianRes->getContent())->toStartWith('%PDF');
});

test('wali lain atau pembayaran milik anak lain ditolak 404 saat mengunduh kuitansi', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    enableBillingForReceipt($admin);

    $mi = Unit::factory()->create(['code' => 'MI']);
    $child1 = Student::factory()->create(['unit_id' => $mi->id]);
    $child2 = Student::factory()->create(['unit_id' => $mi->id]);

    $guardian1User = User::factory()->create();
    $guardian1User->assignRole(RoleEnum::Guardian->value);
    $guardian1 = Guardian::factory()->create(['user_id' => $guardian1User->id]);
    $guardian1->students()->attach($child1->id, ['relation' => 'ayah', 'is_primary' => true]);

    $guardian2User = User::factory()->create();
    $guardian2User->assignRole(RoleEnum::Guardian->value);
    $guardian2 = Guardian::factory()->create(['user_id' => $guardian2User->id]);
    $guardian2->students()->attach($child2->id, ['relation' => 'ibu', 'is_primary' => true]);

    $invoice2 = Invoice::factory()->create(['student_id' => $child2->id, 'amount' => 100000]);
    $payment2 = Payment::query()->create([
        'student_id' => $child2->id,
        'method' => PaymentMethod::Midtrans,
        'amount' => 100000,
        'paid_at' => now(),
        'receipt_number' => 'KW-2026-000500',
        'created_at' => now(),
    ]);
    PaymentAllocation::query()->create([
        'payment_id' => $payment2->id,
        'invoice_id' => $invoice2->id,
        'amount' => 100000,
    ]);

    // Wali 1 mencoba akses rute anak 2 -> 404
    $this->actingAs($guardian1User)
        ->get(route('guardian.students.payments.receipt', [$child2, $payment2]))
        ->assertNotFound();

    // Wali 1 mencoba akses pembayaran anak 2 lewat rute anak 1 -> 404
    $this->actingAs($guardian1User)
        ->get(route('guardian.students.payments.receipt', [$child1, $payment2]))
        ->assertNotFound();
});
