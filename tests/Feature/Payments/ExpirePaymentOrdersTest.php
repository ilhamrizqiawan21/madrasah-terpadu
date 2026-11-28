<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\PaymentOrderPurpose;
use App\Enums\PaymentOrderStatus;
use App\Jobs\ExpirePaymentOrders;
use App\Models\FeeType;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\PaymentOrder;
use App\Models\PaymentOrderItem;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Payments\MidtransNotificationProcessor;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();
    config(['services.midtrans.server_key' => 'SB-Mid-server-EXPIRE-UJI', 'services.midtrans.is_production' => false]);

    $unit = Unit::factory()->create(['code' => 'MI']);
    $this->child = Student::factory()->create(['unit_id' => $unit->id]);
    $this->guardian = Guardian::factory()->create(['user_id' => User::factory()->create()->id]);
    $this->guardian->students()->attach($this->child->id, ['relation' => 'ayah', 'is_primary' => true]);
    $this->feeType = FeeType::factory()->create(['unit_id' => $unit->id]);
});

function expiryTestOrder(string $orderId, string $period, mixed $expiresAt): PaymentOrder
{
    $test = test();
    $invoice = Invoice::factory()->create([
        'student_id' => $test->child->id,
        'fee_type_id' => $test->feeType->id,
        'period' => $period,
        'amount' => 100_000,
        'status' => InvoiceStatus::Unpaid,
    ]);

    $order = PaymentOrder::query()->create([
        'order_id' => $orderId,
        'purpose' => PaymentOrderPurpose::Invoices,
        'student_id' => $test->child->id,
        'guardian_id' => $test->guardian->id,
        'gross_amount' => 100_000,
        'status' => PaymentOrderStatus::Pending,
        'expires_at' => $expiresAt,
    ]);
    PaymentOrderItem::query()->create([
        'payment_order_id' => $order->id,
        'invoice_id' => $invoice->id,
        'description' => 'SPP',
        'amount' => 100_000,
    ]);

    return $order;
}

test('order yang ternyata sudah dibayar dilunasi, bukan ditandai kedaluwarsa', function (): void {
    $paid = expiryTestOrder('INV-EXP-PAID', '2026-01-01', now()->subHour());
    Http::fake(['api.sandbox.midtrans.com/v2/INV-EXP-PAID/status' => Http::response([
        'status_code' => '200', 'transaction_status' => 'settlement', 'gross_amount' => '100000.00', 'transaction_id' => 't1',
    ])]);

    (new ExpirePaymentOrders)->handle(app(MidtransNotificationProcessor::class), app(AuditLogger::class));

    expect($paid->fresh()->status)->toBe(PaymentOrderStatus::Paid);
});

test('order tanpa transaksi di Midtrans atau berstatus expire ditandai kedaluwarsa, yang masih baru dibiarkan', function (): void {
    $notFound = expiryTestOrder('INV-EXP-NF', '2026-02-01', now()->subHour());
    $expired = expiryTestOrder('INV-EXP-EXP', '2026-03-01', now()->subHour());
    $stillPending = expiryTestOrder('INV-EXP-PEND', '2026-04-01', now()->subHour());
    $withinGrace = expiryTestOrder('INV-EXP-GRACE', '2026-05-01', now()->subMinutes(5));
    $fresh = expiryTestOrder('INV-EXP-NEW', '2026-06-01', now()->addHours(20));

    Http::fake([
        'api.sandbox.midtrans.com/v2/INV-EXP-NF/status' => Http::response(['status_code' => '404', 'status_message' => "Transaction doesn't exist."]),
        'api.sandbox.midtrans.com/v2/INV-EXP-EXP/status' => Http::response(['status_code' => '407', 'transaction_status' => 'expire', 'gross_amount' => '100000.00']),
        'api.sandbox.midtrans.com/v2/INV-EXP-PEND/status' => Http::response(['status_code' => '201', 'transaction_status' => 'pending', 'gross_amount' => '100000.00']),
    ]);

    $closed = (new ExpirePaymentOrders)->handle(app(MidtransNotificationProcessor::class), app(AuditLogger::class));

    expect($closed)->toBe(2)
        ->and($notFound->fresh()->status)->toBe(PaymentOrderStatus::Expired)
        ->and($expired->fresh()->status)->toBe(PaymentOrderStatus::Expired)
        ->and($stillPending->fresh()->status)->toBe(PaymentOrderStatus::Pending)
        ->and($withinGrace->fresh()->status)->toBe(PaymentOrderStatus::Pending)
        ->and($fresh->fresh()->status)->toBe(PaymentOrderStatus::Pending);

    Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), 'INV-EXP-GRACE') || str_contains($r->url(), 'INV-EXP-NEW'));
});

test('Midtrans tidak dapat dihubungi tidak menandai kedaluwarsa dan tidak menghentikan order lain', function (): void {
    $down = expiryTestOrder('INV-EXP-DOWN', '2026-02-01', now()->subHour());
    $notFound = expiryTestOrder('INV-EXP-NF2', '2026-03-01', now()->subHour());

    Http::fake([
        'api.sandbox.midtrans.com/v2/INV-EXP-DOWN/status' => Http::response([], 503),
        'api.sandbox.midtrans.com/v2/INV-EXP-NF2/status' => Http::response(['status_code' => '404']),
    ]);

    (new ExpirePaymentOrders)->handle(app(MidtransNotificationProcessor::class), app(AuditLogger::class));

    expect($down->fresh()->status)->toBe(PaymentOrderStatus::Pending)
        ->and($notFound->fresh()->status)->toBe(PaymentOrderStatus::Expired);
});

test('job kedaluwarsa dijadwalkan tiap 15 menit', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e): bool => str_contains((string) $e->description, ExpirePaymentOrders::class));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/15 * * * *');
});
