<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentOrderPurpose;
use App\Enums\PaymentOrderStatus;
use App\Enums\PaymentReviewReason;
use App\Enums\Role as RoleEnum;
use App\Models\FeeType;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\PaymentOrder;
use App\Models\PaymentOrderItem;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\PaymentReconciliationNotification;
use App\Services\Features\FeatureFlags;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();
    config(['services.midtrans.server_key' => 'SB-Mid-server-RECON-UJI', 'services.midtrans.is_production' => false]);
    $this->travelTo(now('Asia/Makassar')->setDate(2026, 11, 28)->setTime(10, 0));

    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleEnum::Admin->value);
    app(FeatureFlags::class)->update([Feature::Billing->value => true, Feature::Midtrans->value => true], $this->admin);

    $this->treasurer = User::factory()->create();
    $this->treasurer->assignRole(RoleEnum::Treasurer->value);

    $unit = Unit::factory()->create(['code' => 'MI']);
    $this->child = Student::factory()->create(['unit_id' => $unit->id, 'name' => 'Siti Rekon']);
    $this->guardian = Guardian::factory()->create(['user_id' => User::factory()->create()->id]);
    $this->guardian->students()->attach($this->child->id, ['relation' => 'ibu', 'is_primary' => true]);
    $this->feeType = FeeType::factory()->create(['unit_id' => $unit->id]);
});

function reconTestOrder(string $orderId, string $period, PaymentOrderStatus $status = PaymentOrderStatus::Pending): PaymentOrder
{
    $test = test();
    $invoice = Invoice::factory()->create([
        'student_id' => $test->child->id,
        'fee_type_id' => $test->feeType->id,
        'period' => $period,
        'amount' => 120_000,
        'status' => InvoiceStatus::Unpaid,
    ]);
    $order = PaymentOrder::query()->create([
        'order_id' => $orderId,
        'purpose' => PaymentOrderPurpose::Invoices,
        'student_id' => $test->child->id,
        'guardian_id' => $test->guardian->id,
        'gross_amount' => 120_000,
        'status' => $status,
        'expires_at' => now()->addDay(),
    ]);
    PaymentOrderItem::query()->create([
        'payment_order_id' => $order->id,
        'invoice_id' => $invoice->id,
        'description' => 'SPP Rekon',
        'amount' => 120_000,
    ]);

    return $order;
}

test('halaman pembayaran online hanya untuk pemegang izin rekonsiliasi', function (): void {
    $this->get(route('admin.online-payments.index'))->assertRedirect(route('login'));

    $teacher = User::factory()->create();
    $teacher->assignRole(RoleEnum::Teacher->value);
    $this->actingAs($teacher)->get(route('admin.online-payments.index'))->assertForbidden();

    $this->actingAs($this->treasurer)->get(route('admin.online-payments.index'))->assertOk();

    app(FeatureFlags::class)->update([Feature::Midtrans->value => false], $this->admin);
    $this->actingAs($this->treasurer)->get(route('admin.online-payments.index'))->assertNotFound();
});

test('rekonsiliasi harian mendeteksi selisih dan memberi tahu bendahara', function (): void {
    $this->travelTo(now('Asia/Makassar')->setDate(2026, 11, 27)->setTime(9, 0));
    $missedWebhook = reconTestOrder('INV-REC-MISSED', '2026-01-01');
    $consistent = reconTestOrder('INV-REC-OK', '2026-02-01');
    $this->travelTo(now('Asia/Makassar')->setDate(2026, 11, 28)->setTime(1, 0));

    Http::fake([
        'api.sandbox.midtrans.com/v2/INV-REC-MISSED/status' => Http::response([
            'status_code' => '200', 'transaction_status' => 'settlement', 'gross_amount' => '120000.00', 'transaction_id' => 'x',
        ]),
        'api.sandbox.midtrans.com/v2/INV-REC-OK/status' => Http::response([
            'status_code' => '201', 'transaction_status' => 'pending', 'gross_amount' => '120000.00',
        ]),
    ]);

    $this->artisan('payments:reconcile')
        ->expectsOutputToContain('2 order dicek, 1 selisih')
        ->assertSuccessful();

    expect($missedWebhook->fresh()->status)->toBe(PaymentOrderStatus::Paid)
        ->and($consistent->fresh()->status)->toBe(PaymentOrderStatus::Pending);

    Notification::assertSentTo($this->treasurer, PaymentReconciliationNotification::class);
});

test('rekonsiliasi tanpa selisih tidak mengirim notifikasi dan tanggal salah ditolak', function (): void {
    $this->artisan('payments:reconcile', ['date' => '2026-11-20'])
        ->expectsOutputToContain('0 order dicek, 0 selisih')
        ->assertSuccessful();
    $this->artisan('payments:reconcile', ['date' => '28-11-2026'])->assertFailed();

    Notification::assertNothingSent();
});

test('order lunas yang di Midtrans tidak lagi settlement dilaporkan sebagai selisih', function (): void {
    $this->travelTo(now('Asia/Makassar')->setDate(2026, 11, 27)->setTime(9, 0));
    $order = reconTestOrder('INV-REC-PAID', '2026-03-01', PaymentOrderStatus::Paid);
    $this->travelBack();
    $this->travelTo(now('Asia/Makassar')->setDate(2026, 11, 28)->setTime(1, 0));

    Http::fake(['api.sandbox.midtrans.com/*' => Http::response([
        'status_code' => '407', 'transaction_status' => 'expire', 'gross_amount' => '120000.00',
    ])]);

    $this->artisan('payments:reconcile', ['date' => '2026-11-27'])
        ->expectsOutputToContain('1 selisih')
        ->assertSuccessful();

    expect($order->fresh()->status)->toBe(PaymentOrderStatus::Paid);
    Notification::assertSentTo($this->treasurer, PaymentReconciliationNotification::class);
});

test('bendahara melihat detail tanpa data sensitif, mengecek status, dan menandai sudah ditinjau', function (): void {
    $order = reconTestOrder('INV-REC-UI', '2026-04-01');
    $order->forceFill([
        'review_reason' => PaymentReviewReason::AmountMismatch,
        'last_status_payload' => ['transaction_status' => 'settlement', 'gross_amount' => '1.00', 'masked_card' => '4811-11XX-XXXX-1114', 'signature_key' => 'rahasia'],
    ])->save();

    $this->actingAs($this->treasurer)
        ->get(route('admin.online-payments.index', ['order' => 'INV-REC-UI']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/billing/online-payments')
            ->where('filters.tab', 'review')
            ->where('counts.review', 1)
            ->where('orders.data.0.order_id', 'INV-REC-UI')
            ->where('orders.data.0.review_reason', 'amount_mismatch')
            ->where('selected.midtrans.gross_amount', '1.00')
            ->missing('selected.midtrans.masked_card')
            ->missing('selected.midtrans.signature_key'));

    Http::fake(['api.sandbox.midtrans.com/*' => Http::response([
        'status_code' => '201', 'transaction_status' => 'pending', 'gross_amount' => '120000.00',
    ])]);
    $this->actingAs($this->treasurer)
        ->post(route('admin.online-payments.check', 'INV-REC-UI'))
        ->assertRedirect();
    $this->assertDatabaseHas('audit_logs', ['action' => 'payment_order.checked', 'subject_id' => $order->id, 'user_id' => $this->treasurer->id]);

    $this->actingAs($this->treasurer)
        ->post(route('admin.online-payments.review', 'INV-REC-UI'))
        ->assertRedirect();

    $fresh = $order->fresh();
    expect($fresh->reviewed_at)->not->toBeNull()
        ->and($fresh->reviewed_by)->toBe($this->treasurer->id);

    $this->actingAs($this->treasurer)
        ->post(route('admin.online-payments.review', 'INV-REC-UI'))
        ->assertSessionHasErrors('order');
});

test('rekonsiliasi dijadwalkan harian pukul 01.00 WITA', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e): bool => str_contains((string) $e->command, 'payments:reconcile'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 1 * * *')
        ->and($event->timezone)->toBe('Asia/Makassar');
});

test('rekonsiliasi mencakup order sampai pukul 23.59 WITA pada tanggalnya', function (): void {
    $this->travelTo(now('Asia/Makassar')->setDate(2026, 11, 27)->setTime(23, 30));
    $late = reconTestOrder('INV-REC-MALAM', '2026-05-01');
    $this->travelTo(now('Asia/Makassar')->setDate(2026, 11, 28)->setTime(0, 30));
    $nextDay = reconTestOrder('INV-REC-BESOK', '2026-06-01');

    Http::fake(['api.sandbox.midtrans.com/*' => Http::response([
        'status_code' => '201', 'transaction_status' => 'pending', 'gross_amount' => '120000.00',
    ])]);

    $this->artisan('payments:reconcile', ['date' => '2026-11-27'])
        ->expectsOutputToContain('1 order dicek')
        ->assertSuccessful();

    Http::assertSent(fn ($r): bool => str_contains($r->url(), 'INV-REC-MALAM'));
    Http::assertNotSent(fn ($r): bool => str_contains($r->url(), 'INV-REC-BESOK'));
    expect($late->fresh())->not->toBeNull()->and($nextDay->fresh())->not->toBeNull();
});
