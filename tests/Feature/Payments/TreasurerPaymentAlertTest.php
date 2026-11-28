<?php

declare(strict_types=1);

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
use App\Notifications\PaymentReviewRequiredNotification;
use App\Services\Payments\PaymentReconciliationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();
    config(['services.midtrans.server_key' => 'SB-Mid-server-ALERT-UJI', 'services.midtrans.is_production' => false]);

    $this->treasurer = User::factory()->create();
    $this->treasurer->assignRole(RoleEnum::Treasurer->value);
    $this->inactiveTreasurer = User::factory()->create(['is_active' => false]);
    $this->inactiveTreasurer->assignRole(RoleEnum::Treasurer->value);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleEnum::Admin->value);

    $unit = Unit::factory()->create(['code' => 'MI']);
    $this->child = Student::factory()->create(['unit_id' => $unit->id]);
    $this->guardianUser = User::factory()->create();
    $guardian = Guardian::factory()->create(['user_id' => $this->guardianUser->id]);
    $guardian->students()->attach($this->child->id, ['relation' => 'ayah', 'is_primary' => true]);

    $this->invoice = Invoice::factory()->create([
        'student_id' => $this->child->id,
        'fee_type_id' => FeeType::factory()->create(['unit_id' => $unit->id])->id,
        'amount' => 80_000,
        'status' => InvoiceStatus::Unpaid,
    ]);
    $this->order = PaymentOrder::query()->create([
        'order_id' => 'INV-ALERT-1',
        'purpose' => PaymentOrderPurpose::Invoices,
        'student_id' => $this->child->id,
        'guardian_id' => $guardian->id,
        'gross_amount' => 80_000,
        'status' => PaymentOrderStatus::Pending,
        'expires_at' => now()->addDay(),
    ]);
    PaymentOrderItem::query()->create([
        'payment_order_id' => $this->order->id,
        'invoice_id' => $this->invoice->id,
        'description' => 'SPP',
        'amount' => 80_000,
    ]);
});

/**
 * @param  array<string, string>  $status
 */
function alertAfterStatus(array $status): void
{
    Http::fake(['api.sandbox.midtrans.com/*' => Http::response(array_merge(['status_code' => '200', 'gross_amount' => '80000.00'], $status))]);
    app(PaymentReconciliationService::class)->reconcileOrder(test()->order);
}

test('setiap jenis peringatan dikirim hanya ke bendahara aktif dengan alasan yang benar', function (array $status, ?Closure $prepare, PaymentReviewReason $reason): void {
    if ($prepare !== null) {
        $prepare($this);
    }

    alertAfterStatus($status);

    expect($this->order->fresh()->review_reason)->toBe($reason);

    Notification::assertSentTo(
        $this->treasurer,
        PaymentReviewRequiredNotification::class,
        fn (PaymentReviewRequiredNotification $n): bool => $n->toArray($this->treasurer)['reason'] === $reason->value
            && $n->toArray($this->treasurer)['order_id'] === 'INV-ALERT-1',
    );
    Notification::assertNotSentTo($this->inactiveTreasurer, PaymentReviewRequiredNotification::class);
    Notification::assertNotSentTo($this->admin, PaymentReviewRequiredNotification::class);
    Notification::assertNotSentTo($this->guardianUser, PaymentReviewRequiredNotification::class);
})->with([
    'nominal tidak cocok' => [['transaction_status' => 'settlement', 'gross_amount' => '8000.00'], null, PaymentReviewReason::AmountMismatch],
    'refund' => [['transaction_status' => 'refund'], fn ($t) => $t->order->forceFill(['status' => PaymentOrderStatus::Paid])->save(), PaymentReviewReason::NeedsReview],
    'chargeback' => [['transaction_status' => 'chargeback'], fn ($t) => $t->order->forceFill(['status' => PaymentOrderStatus::Paid])->save(), PaymentReviewReason::NeedsReview],
    'kelebihan bayar' => [['transaction_status' => 'settlement'], fn ($t) => $t->invoice->forceFill(['paid_total' => 80_000, 'status' => InvoiceStatus::Paid])->save(), PaymentReviewReason::Overpaid],
    'fraud challenge' => [['transaction_status' => 'capture', 'fraud_status' => 'challenge'], null, PaymentReviewReason::Challenge],
]);

test('status biasa tidak memicu peringatan', function (): void {
    alertAfterStatus(['transaction_status' => 'settlement']);

    expect($this->order->fresh()->review_reason)->toBeNull();
    Notification::assertNotSentTo($this->treasurer, PaymentReviewRequiredNotification::class);
});
