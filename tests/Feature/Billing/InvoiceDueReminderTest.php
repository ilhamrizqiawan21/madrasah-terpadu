<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\InvoiceStatus;
use App\Enums\Role as RoleEnum;
use App\Jobs\SendInvoiceDueReminders;
use App\Models\FeeType;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\InvoiceDueReminderNotification;
use App\Services\Features\FeatureFlags;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('pengingat jatuh tempo mengirim notifikasi H-3 dan H+1 secara idempoten saat fitur billing aktif', function (): void {
    Notification::fake();
    $this->travelTo(now('Asia/Makassar')->setDate(2026, 11, 10)->setTime(7, 0, 0));

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    app(FeatureFlags::class)->update([Feature::Billing->value => true], $admin);

    $mi = Unit::factory()->create(['code' => 'MI']);
    $student = Student::factory()->create(['unit_id' => $mi->id, 'name' => 'Bilal']);
    $guardianUser = User::factory()->create();
    $guardianUser->assignRole(RoleEnum::Guardian->value);
    $guardian = Guardian::factory()->create(['user_id' => $guardianUser->id]);
    $guardian->students()->attach($student->id, ['relation' => 'ayah', 'is_primary' => true]);

    $f1 = FeeType::factory()->create(['unit_id' => $mi->id]);
    $f2 = FeeType::factory()->create(['unit_id' => $mi->id]);
    $f3 = FeeType::factory()->create(['unit_id' => $mi->id]);
    $f4 = FeeType::factory()->create(['unit_id' => $mi->id]);

    // H-3 (jatuh tempo 13 Nov) -> harus terkirim
    $hMinus3 = Invoice::factory()->create([
        'student_id' => $student->id,
        'fee_type_id' => $f1->id,
        'due_date' => '2026-11-13',
        'status' => InvoiceStatus::Unpaid,
    ]);

    // H+1 (jatuh tempo 9 Nov) -> harus terkirim
    $hPlus1 = Invoice::factory()->create([
        'student_id' => $student->id,
        'fee_type_id' => $f2->id,
        'due_date' => '2026-11-09',
        'status' => InvoiceStatus::Partial,
        'paid_total' => 50000,
    ]);

    // Lunas di H+1 -> tidak terkirim
    Invoice::factory()->create([
        'student_id' => $student->id,
        'fee_type_id' => $f3->id,
        'due_date' => '2026-11-09',
        'status' => InvoiceStatus::Paid,
        'paid_total' => 150000,
    ]);

    // Tanggal lain (H-2) -> tidak terkirim
    Invoice::factory()->create([
        'student_id' => $student->id,
        'fee_type_id' => $f4->id,
        'due_date' => '2026-11-12',
        'status' => InvoiceStatus::Unpaid,
    ]);

    $sentFirst = app()->call([new SendInvoiceDueReminders, 'handle']);
    expect($sentFirst)->toBe(2);

    Notification::assertSentToTimes($guardianUser, InvoiceDueReminderNotification::class, 2);
    expect(DB::table('invoice_reminders')->where('invoice_id', $hMinus3->id)->where('kind', 'before_due')->exists())->toBeTrue()
        ->and(DB::table('invoice_reminders')->where('invoice_id', $hPlus1->id)->where('kind', 'overdue')->exists())->toBeTrue();

    // Dijalankan ulang di hari yang sama -> 0 notifikasi baru (idempoten)
    $sentSecond = app()->call([new SendInvoiceDueReminders, 'handle']);
    expect($sentSecond)->toBe(0);
    Notification::assertSentToTimes($guardianUser, InvoiceDueReminderNotification::class, 2);
});

test('pengingat jatuh tempo tidak berjalan bila kunci fitur billing nonaktif', function (): void {
    Notification::fake();
    $this->travelTo(now('Asia/Makassar')->setDate(2026, 11, 10)->setTime(7, 0, 0));

    $mi = Unit::factory()->create(['code' => 'MI']);
    $student = Student::factory()->create(['unit_id' => $mi->id]);
    Invoice::factory()->create([
        'student_id' => $student->id,
        'due_date' => '2026-11-13',
        'status' => InvoiceStatus::Unpaid,
    ]);

    $sent = app()->call([new SendInvoiceDueReminders, 'handle']);
    expect($sent)->toBe(0);
    Notification::assertNothingSent();
});
