<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role as RoleEnum;
use App\Models\FeeType;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Billing\ManualPaymentService;
use App\Services\Features\FeatureFlags;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('portal tagihan wali menampilkan daftar per anak urut jatuh tempo terdekat dan total keluarga sama dengan jumlah sisa semua anak', function (): void {
    $this->travelTo(now('Asia/Makassar')->setDate(2026, 11, 15)->setTime(10, 0, 0));

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    app(FeatureFlags::class)->update([Feature::Billing->value => true], $admin);

    $mi = Unit::factory()->create(['code' => 'MI']);
    $child1 = Student::factory()->create(['unit_id' => $mi->id, 'name' => 'Anak Pertama']);
    $child2 = Student::factory()->create(['unit_id' => $mi->id, 'name' => 'Anak Kedua']);
    $otherChild = Student::factory()->create(['unit_id' => $mi->id, 'name' => 'Anak Orang Lain']);

    $guardianUser = User::factory()->create();
    $guardianUser->assignRole(RoleEnum::Guardian->value);
    $guardian = Guardian::factory()->create(['user_id' => $guardianUser->id]);
    $guardian->students()->attach($child1->id, ['relation' => 'ayah', 'is_primary' => true]);
    $guardian->students()->attach($child2->id, ['relation' => 'ayah', 'is_primary' => true]);

    $f1 = FeeType::factory()->create(['unit_id' => $mi->id]);
    $f2 = FeeType::factory()->create(['unit_id' => $mi->id]);

    // Anak 1: 1 menunggak (10 Nov, sisa 100.000) + 1 belum jatuh tempo (20 Nov, sisa 150.000)
    $invLate = Invoice::factory()->create([
        'student_id' => $child1->id,
        'fee_type_id' => $f1->id,
        'description' => 'SPP November',
        'amount' => 150000,
        'paid_total' => 50000,
        'status' => InvoiceStatus::Partial,
        'due_date' => '2026-11-10',
    ]);
    $invFuture = Invoice::factory()->create([
        'student_id' => $child1->id,
        'fee_type_id' => $f2->id,
        'description' => 'Kegiatan Desember',
        'amount' => 150000,
        'status' => InvoiceStatus::Unpaid,
        'due_date' => '2026-11-20',
    ]);

    // Anak 2: sisa 200.000
    Invoice::factory()->create([
        'student_id' => $child2->id,
        'fee_type_id' => $f1->id,
        'amount' => 200000,
        'status' => InvoiceStatus::Unpaid,
        'due_date' => '2026-11-18',
    ]);

    // Anak orang lain tidak boleh ikut terhitung
    Invoice::factory()->create([
        'student_id' => $otherChild->id,
        'fee_type_id' => $f1->id,
        'amount' => 999000,
        'status' => InvoiceStatus::Unpaid,
    ]);

    // Satu pembayaran agar muncul di riwayat + kuitansi
    app(ManualPaymentService::class)->receive($admin, $child1, PaymentMethod::Cash, [
        ['invoice_id' => $invFuture->id, 'amount' => 50000],
    ]);

    // Total keluarga = (100.000 + 100.000) + 200.000 = 400.000
    $this->actingAs($guardianUser)
        ->get(route('guardian.billing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('guardian/billing')
            ->where('familyTotalRemaining', 400000)
            ->where('midtransEnabled', false)
            ->has('children', 2)
            ->where('children.1.open_invoices.0.id', $invLate->id)
            ->where('children.1.open_invoices.0.is_overdue', true)
            ->has('children.1.payments', 1));
});
