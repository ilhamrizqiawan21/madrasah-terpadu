<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role as RoleEnum;
use App\Enums\TransferProofStatus;
use App\Models\AuditLog;
use App\Models\FeeType;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\TransferProof;
use App\Models\Unit;
use App\Models\User;
use App\Services\Features\FeatureFlags;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
});

function enableBillingForTransferProof(User $actor): void
{
    app(FeatureFlags::class)->update([
        Feature::Billing->value => true,
    ], $actor);
}

test('wali hanya dapat mengunggah bukti transfer untuk tagihan anaknya sendiri ke disk privat', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    enableBillingForTransferProof($admin);

    $mi = Unit::factory()->create(['code' => 'MI']);
    $child1 = Student::factory()->create(['unit_id' => $mi->id]);
    $child2 = Student::factory()->create(['unit_id' => $mi->id]);

    $guardian1User = User::factory()->create();
    $guardian1User->assignRole(RoleEnum::Guardian->value);
    $guardian1 = Guardian::factory()->create(['user_id' => $guardian1User->id]);
    $guardian1->students()->attach($child1->id, ['relation' => 'ibu', 'is_primary' => true]);

    $feeType = FeeType::factory()->create(['unit_id' => $mi->id]);
    $inv1 = Invoice::factory()->create(['student_id' => $child1->id, 'fee_type_id' => $feeType->id, 'amount' => 150000]);
    $inv2 = Invoice::factory()->create(['student_id' => $child2->id, 'fee_type_id' => $feeType->id, 'amount' => 150000]);

    $photo = UploadedFile::fake()->image('bukti.jpg', 600, 800);

    // Anak lain -> 404
    $this->actingAs($guardian1User)
        ->post(route('guardian.students.invoices.transfer-proof.store', [$child2, $inv2]), [
            'amount' => 150000,
            'photo' => $photo,
        ])
        ->assertNotFound();

    // Tagihan anak lain lewat rute anak sendiri -> 404
    $this->actingAs($guardian1User)
        ->post(route('guardian.students.invoices.transfer-proof.store', [$child1, $inv2]), [
            'amount' => 150000,
            'photo' => $photo,
        ])
        ->assertNotFound();

    // Anak sendiri -> sukses tersimpan di disk privat
    $this->actingAs($guardian1User)
        ->post(route('guardian.students.invoices.transfer-proof.store', [$child1, $inv1]), [
            'amount' => 150000,
            'notes' => 'Transfer BSI',
            'photo' => $photo,
        ])
        ->assertRedirect();

    $proof = TransferProof::query()->firstOrFail();
    expect($proof->status)->toBe(TransferProofStatus::Pending)
        ->and($proof->amount)->toBe(150000);

    Storage::disk('local')->assertExists($proof->proof_path);
});

test('bendahara dapat memverifikasi bukti transfer menjadi pembayaran dan verifikasi ganda ditolak', function (): void {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    enableBillingForTransferProof($admin);

    $treasurer = User::factory()->create();
    $treasurer->assignRole(RoleEnum::Treasurer->value);

    $unitHead = User::factory()->create();
    $unitHead->assignRole(RoleEnum::UnitHead->value);

    $this->actingAs($unitHead)
        ->get(route('admin.transfer-proofs.index'))
        ->assertForbidden();

    $mi = Unit::factory()->create(['code' => 'MI']);
    $student = Student::factory()->create(['unit_id' => $mi->id]);
    $guardianUser = User::factory()->create();
    $guardianUser->assignRole(RoleEnum::Guardian->value);
    $guardian = Guardian::factory()->create(['user_id' => $guardianUser->id]);
    $guardian->students()->attach($student->id, ['relation' => 'ayah', 'is_primary' => true]);

    $invoice = Invoice::factory()->create(['student_id' => $student->id, 'amount' => 200000]);

    $this->actingAs($guardianUser)
        ->post(route('guardian.students.invoices.transfer-proof.store', [$student, $invoice]), [
            'amount' => 200000,
            'photo' => UploadedFile::fake()->image('bukti.png', 500, 500),
        ])
        ->assertRedirect();

    $proof = TransferProof::query()->firstOrFail();

    $this->actingAs($treasurer)
        ->get(route('admin.transfer-proofs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/billing/transfer-proofs')
            ->has('proofs.data', 1));

    // Verifikasi pertama -> membuat pembayaran transfer dan melunasi tagihan
    $this->actingAs($treasurer)
        ->post(route('admin.transfer-proofs.approve', $proof))
        ->assertRedirect();

    $proof->refresh();
    expect($proof->status)->toBe(TransferProofStatus::Approved)
        ->and($proof->payment_id)->not->toBeNull();

    $payment = Payment::query()->findOrFail($proof->payment_id);
    expect($payment->method)->toBe(PaymentMethod::Transfer)
        ->and($payment->amount)->toBe(200000)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);

    // Verifikasi kedua ditolak dan tidak membuat pembayaran ganda
    $this->actingAs($treasurer)
        ->post(route('admin.transfer-proofs.approve', $proof))
        ->assertSessionHasErrors('proof');

    expect(Payment::query()->count())->toBe(1);
    expect(AuditLog::query()->where('action', 'transfer_proof.approved')->where('subject_id', $proof->id)->exists())->toBeTrue();
});

test('bendahara dapat menolak bukti transfer dengan alasan wajib', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    enableBillingForTransferProof($admin);

    $treasurer = User::factory()->create();
    $treasurer->assignRole(RoleEnum::Treasurer->value);

    $mi = Unit::factory()->create(['code' => 'MI']);
    $student = Student::factory()->create(['unit_id' => $mi->id]);
    $guardianUser = User::factory()->create();
    $guardianUser->assignRole(RoleEnum::Guardian->value);
    $guardian = Guardian::factory()->create(['user_id' => $guardianUser->id]);
    $guardian->students()->attach($student->id, ['relation' => 'ibu', 'is_primary' => true]);

    $invoice = Invoice::factory()->create(['student_id' => $student->id, 'amount' => 150000]);

    $this->actingAs($guardianUser)
        ->post(route('guardian.students.invoices.transfer-proof.store', [$student, $invoice]), [
            'amount' => 150000,
            'photo' => UploadedFile::fake()->image('buram.jpg', 400, 400),
        ])
        ->assertRedirect();

    $proof = TransferProof::query()->firstOrFail();

    $this->actingAs($treasurer)
        ->post(route('admin.transfer-proofs.reject', $proof), [
            'reason' => 'Foto buram, nominal tidak terbaca',
        ])
        ->assertRedirect();

    expect($proof->fresh()->status)->toBe(TransferProofStatus::Rejected)
        ->and($proof->fresh()->rejection_reason)->toBe('Foto buram, nominal tidak terbaca')
        ->and(Payment::query()->count())->toBe(0);
});
