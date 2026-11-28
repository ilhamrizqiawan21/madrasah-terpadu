<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentOrderPurpose;
use App\Enums\PaymentOrderStatus;
use App\Enums\PaymentReviewReason;
use App\Enums\Role as RoleEnum;
use App\Enums\WalletEntryType;
use App\Exceptions\Payments\MidtransUnavailableException;
use App\Models\FeeType;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentNotification;
use App\Models\PaymentOrder;
use App\Models\PaymentOrderItem;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletEntry;
use App\Notifications\InvoicePaidNotification;
use App\Notifications\PaymentReviewRequiredNotification;
use App\Notifications\WalletTopupNotification;
use App\Services\Billing\ManualPaymentService;
use App\Services\Features\FeatureFlags;
use App\Services\Payments\MidtransNotificationProcessor;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

const WEBHOOK_SERVER_KEY = 'SB-Mid-server-WEBHOOK-UJI';

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();

    config([
        'services.midtrans.server_key' => WEBHOOK_SERVER_KEY,
        'services.midtrans.is_production' => false,
    ]);

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    app(FeatureFlags::class)->update([
        Feature::Billing->value => true,
        Feature::Midtrans->value => true,
        Feature::Wallet->value => true,
    ], $admin);

    $this->treasurer = User::factory()->create();
    $this->treasurer->assignRole(RoleEnum::Treasurer->value);

    $unit = Unit::factory()->create(['code' => 'MI']);
    $this->child = Student::factory()->create(['unit_id' => $unit->id]);

    $this->guardianUser = User::factory()->create();
    $this->guardianUser->assignRole(RoleEnum::Guardian->value);
    $this->guardian = Guardian::factory()->create(['user_id' => $this->guardianUser->id]);
    $this->guardian->students()->attach($this->child->id, ['relation' => 'ibu', 'is_primary' => true]);

    $this->invoice = Invoice::factory()->create([
        'student_id' => $this->child->id,
        'fee_type_id' => FeeType::factory()->create(['unit_id' => $unit->id])->id,
        'description' => 'SPP November 2026',
        'amount' => 150_000,
        'status' => InvoiceStatus::Unpaid,
    ]);

    $this->order = PaymentOrder::query()->create([
        'order_id' => 'INV-01JWEBHOOKUJI',
        'purpose' => PaymentOrderPurpose::Invoices,
        'student_id' => $this->child->id,
        'guardian_id' => $this->guardian->id,
        'gross_amount' => 150_000,
        'status' => PaymentOrderStatus::Pending,
        'snap_token' => 'tok',
        'expires_at' => now()->addDay(),
    ]);
    PaymentOrderItem::query()->create([
        'payment_order_id' => $this->order->id,
        'invoice_id' => $this->invoice->id,
        'description' => 'SPP November 2026',
        'amount' => 150_000,
    ]);
});

/**
 * @return array<string, string>
 */
function webhookBody(string $orderId, string $status = 'settlement', string $gross = '150000.00', string $statusCode = '200'): array
{
    return [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $gross,
        'transaction_status' => $status,
        'signature_key' => hash('sha512', $orderId.$statusCode.$gross.WEBHOOK_SERVER_KEY),
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function midtransStatusBody(string $status, string $gross = '150000.00', array $overrides = []): array
{
    return array_merge([
        'status_code' => '200',
        'transaction_id' => 'trx-123',
        'transaction_status' => $status,
        'gross_amount' => $gross,
        'payment_type' => 'qris',
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function fakeMidtransStatus(string $status, string $gross = '150000.00', array $overrides = []): void
{
    Http::fake([
        'api.sandbox.midtrans.com/v2/*' => Http::response(midtransStatusBody($status, $gross, $overrides)),
    ]);
}

test('settlement valid melunasi tagihan, membuat kuitansi, dan memberi tahu wali (§6 no. 1)', function (): void {
    fakeMidtransStatus('settlement');

    $this->postJson(route('webhooks.midtrans'), webhookBody('INV-01JWEBHOOKUJI'))
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    $order = $this->order->fresh();
    expect($order->status)->toBe(PaymentOrderStatus::Paid)
        ->and($order->paid_at)->not->toBeNull()
        ->and($order->payment_type)->toBe('qris')
        ->and($order->midtrans_transaction_id)->toBe('trx-123')
        ->and($this->invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($this->invoice->fresh()->paid_total)->toBe(150_000);

    $payment = Payment::query()->sole();
    expect($payment->method)->toBe(PaymentMethod::Midtrans)
        ->and($payment->payment_order_id)->toBe($order->id)
        ->and($payment->received_by)->toBeNull()
        ->and($payment->receipt_number)->toStartWith('KW-')
        ->and($payment->allocations()->sole()->amount)->toBe(150_000);

    $notification = PaymentNotification::query()->sole();
    expect($notification->signature_valid)->toBeTrue()
        ->and($notification->process_result)->toBe('paid')
        ->and($notification->processed_at)->not->toBeNull();

    Notification::assertSentTo($this->guardianUser, InvoicePaidNotification::class);
    $this->assertDatabaseHas('audit_logs', ['action' => 'payment_order.paid', 'subject_id' => $order->id]);
});

test('webhook yang sama dua kali hanya membuat satu pembayaran (§6 no. 2–3)', function (): void {
    fakeMidtransStatus('settlement');

    $this->postJson(route('webhooks.midtrans'), webhookBody('INV-01JWEBHOOKUJI'))->assertOk();
    $this->postJson(route('webhooks.midtrans'), webhookBody('INV-01JWEBHOOKUJI'))->assertOk();

    expect(Payment::query()->count())->toBe(1)
        ->and($this->invoice->fresh()->paid_total)->toBe(150_000)
        ->and(PaymentNotification::query()->orderBy('id')->pluck('process_result')->all())->toBe(['paid', 'duplicate']);
})->group('mysql');

test('signature salah ditolak 403 tanpa perubahan (§6 no. 4)', function (): void {
    Http::fake();
    $body = webhookBody('INV-01JWEBHOOKUJI');
    $body['signature_key'] = str_repeat('a', 128);

    $this->postJson(route('webhooks.midtrans'), $body)->assertForbidden();

    expect($this->order->fresh()->status)->toBe(PaymentOrderStatus::Pending)
        ->and(Payment::query()->count())->toBe(0)
        ->and(PaymentNotification::query()->sole()->signature_valid)->toBeFalse();
    Http::assertNothingSent();
});

test('signature benar tetapi Get Status masih pending tidak melunasi (§6 no. 5)', function (): void {
    fakeMidtransStatus('pending');

    $this->postJson(route('webhooks.midtrans'), webhookBody('INV-01JWEBHOOKUJI'))->assertOk();

    expect($this->order->fresh()->status)->toBe(PaymentOrderStatus::Pending)
        ->and($this->invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid)
        ->and(PaymentNotification::query()->sole()->process_result)->toBe('pending');
});

test('nominal berbeda ditandai amount_mismatch tanpa melunasi dan bendahara diberi tahu (§6 no. 6)', function (): void {
    fakeMidtransStatus('settlement', '15000.00');

    $this->postJson(route('webhooks.midtrans'), webhookBody('INV-01JWEBHOOKUJI'))->assertOk();

    $order = $this->order->fresh();
    expect($order->status)->toBe(PaymentOrderStatus::Pending)
        ->and($order->review_reason)->toBe(PaymentReviewReason::AmountMismatch)
        ->and($this->invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid)
        ->and(Payment::query()->count())->toBe(0)
        ->and(PaymentNotification::query()->sole()->process_result)->toBe('amount_mismatch');

    Notification::assertSentTo($this->treasurer, PaymentReviewRequiredNotification::class);
});

test('expire menandai order kedaluwarsa sehingga tagihan bebas diorder lagi (§6 no. 7)', function (): void {
    fakeMidtransStatus('expire');

    $this->postJson(route('webhooks.midtrans'), webhookBody('INV-01JWEBHOOKUJI', 'expire', '150000.00', '407'))->assertOk();

    expect($this->order->fresh()->status)->toBe(PaymentOrderStatus::Expired);

    $busy = PaymentOrderItem::query()
        ->where('invoice_id', $this->invoice->id)
        ->whereHas('order', fn ($q) => $q->where('status', PaymentOrderStatus::Pending->value))
        ->exists();
    expect($busy)->toBeFalse();
});

test('settlement yang datang setelah expire tetap melunasi (§6 no. 8)', function (): void {
    $this->order->forceFill(['status' => PaymentOrderStatus::Expired, 'expires_at' => now()->subHour()])->save();
    fakeMidtransStatus('settlement');

    $this->postJson(route('webhooks.midtrans'), webhookBody('INV-01JWEBHOOKUJI'))->assertOk();

    expect($this->order->fresh()->status)->toBe(PaymentOrderStatus::Paid)
        ->and($this->invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

test('order tidak dikenal dicatat dan dijawab 200 tanpa perubahan (§6 no. 9)', function (): void {
    Http::fake();

    $this->postJson(route('webhooks.midtrans'), webhookBody('ORDER-APLIKASI-LAMA-1'))->assertOk();

    expect(PaymentNotification::query()->sole()->process_result)->toBe('unknown_order')
        ->and($this->order->fresh()->status)->toBe(PaymentOrderStatus::Pending);
    Http::assertNothingSent();
});

test('tagihan yang sudah dilunasi kasir: kelebihan masuk saldo kantin dan bendahara diberi tahu (§6 no. 10)', function (): void {
    app(ManualPaymentService::class)->receive(
        $this->treasurer,
        $this->child,
        PaymentMethod::Cash,
        [['invoice_id' => $this->invoice->id, 'amount' => 150_000]],
    );
    fakeMidtransStatus('settlement');

    $this->postJson(route('webhooks.midtrans'), webhookBody('INV-01JWEBHOOKUJI'))->assertOk();

    $order = $this->order->fresh();
    expect($order->status)->toBe(PaymentOrderStatus::Paid)
        ->and($order->review_reason)->toBe(PaymentReviewReason::Overpaid)
        ->and(Payment::query()->where('method', PaymentMethod::Midtrans->value)->count())->toBe(0)
        ->and($this->invoice->fresh()->paid_total)->toBe(150_000);

    $entry = WalletEntry::query()->sole();
    expect($entry->type)->toBe(WalletEntryType::Adjustment)
        ->and($entry->amount)->toBe(150_000)
        ->and($entry->idempotency_key)->toBe('midtrans-overpaid:INV-01JWEBHOOKUJI');

    Notification::assertSentTo($this->treasurer, PaymentReviewRequiredNotification::class);
});

test('kelebihan bayar tidak masuk saldo bila flag dimatikan, hanya ditandai tinjau', function (): void {
    config(['sekolah.payments.overpayment_to_wallet' => false]);
    $this->invoice->forceFill(['status' => InvoiceStatus::Cancelled])->save();
    fakeMidtransStatus('settlement');

    $this->postJson(route('webhooks.midtrans'), webhookBody('INV-01JWEBHOOKUJI'))->assertOk();

    expect($this->order->fresh()->review_reason)->toBe(PaymentReviewReason::Overpaid)
        ->and(WalletEntry::query()->count())->toBe(0);
    Notification::assertSentTo($this->treasurer, PaymentReviewRequiredNotification::class);
});

test('top-up settlement menambah saldo sekali walau webhook datang dua kali (§6 no. 11)', function (): void {
    $topUp = PaymentOrder::query()->create([
        'order_id' => 'TOP-01JWEBHOOKUJI',
        'purpose' => PaymentOrderPurpose::WalletTopup,
        'student_id' => $this->child->id,
        'guardian_id' => $this->guardian->id,
        'gross_amount' => 50_000,
        'status' => PaymentOrderStatus::Pending,
        'expires_at' => now()->addDay(),
    ]);
    PaymentOrderItem::query()->create([
        'payment_order_id' => $topUp->id,
        'invoice_id' => null,
        'description' => 'Isi saldo kantin',
        'amount' => 50_000,
    ]);
    fakeMidtransStatus('settlement', '50000.00');

    $this->postJson(route('webhooks.midtrans'), webhookBody('TOP-01JWEBHOOKUJI', 'settlement', '50000.00'))->assertOk();
    $this->postJson(route('webhooks.midtrans'), webhookBody('TOP-01JWEBHOOKUJI', 'settlement', '50000.00'))->assertOk();

    $wallet = Wallet::query()->where('student_id', $this->child->id)->sole();
    expect($wallet->balance)->toBe(50_000)
        ->and(WalletEntry::query()->count())->toBe(1)
        ->and(WalletEntry::query()->sole()->idempotency_key)->toBe('midtrans:TOP-01JWEBHOOKUJI')
        ->and($topUp->fresh()->status)->toBe(PaymentOrderStatus::Paid);

    Notification::assertSentTo($this->guardianUser, WalletTopupNotification::class);
});

test('capture dengan challenge tetap pending dan masuk antrean tinjau (§6 no. 14)', function (): void {
    fakeMidtransStatus('capture', '150000.00', ['fraud_status' => 'challenge']);

    $this->postJson(route('webhooks.midtrans'), webhookBody('INV-01JWEBHOOKUJI', 'capture'))->assertOk();

    $order = $this->order->fresh();
    expect($order->status)->toBe(PaymentOrderStatus::Pending)
        ->and($order->review_reason)->toBe(PaymentReviewReason::Challenge)
        ->and($this->invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid);
});

test('refund pada order lunas tidak diotomatisasi dan ditandai needs_review', function (): void {
    Http::fakeSequence('api.sandbox.midtrans.com/*')
        ->push(midtransStatusBody('settlement'))
        ->push(midtransStatusBody('refund'));

    $this->postJson(route('webhooks.midtrans'), webhookBody('INV-01JWEBHOOKUJI'))->assertOk();
    $this->postJson(route('webhooks.midtrans'), webhookBody('INV-01JWEBHOOKUJI', 'refund'))->assertOk();

    $order = $this->order->fresh();
    expect($order->status)->toBe(PaymentOrderStatus::Paid)
        ->and($order->review_reason)->toBe(PaymentReviewReason::NeedsReview)
        ->and($this->invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

test('Get Status gagal membuat job gagal tanpa menyimpan perubahan agar diulang antrean', function (): void {
    Http::fake(['api.sandbox.midtrans.com/*' => Http::response([], 503)]);

    $notification = PaymentNotification::query()->create([
        'order_id' => 'INV-01JWEBHOOKUJI',
        'transaction_status' => 'settlement',
        'status_code' => '200',
        'signature_valid' => true,
        'payload' => webhookBody('INV-01JWEBHOOKUJI'),
        'received_at' => now(),
    ]);

    expect(fn () => app(MidtransNotificationProcessor::class)->process($notification))
        ->toThrow(MidtransUnavailableException::class);

    expect($notification->fresh()->processed_at)->toBeNull()
        ->and($this->order->fresh()->status)->toBe(PaymentOrderStatus::Pending);
});
