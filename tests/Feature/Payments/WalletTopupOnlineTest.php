<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\PaymentOrderPurpose;
use App\Enums\PaymentOrderStatus;
use App\Enums\Role as RoleEnum;
use App\Models\Guardian;
use App\Models\PaymentOrder;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletEntry;
use App\Services\Features\FeatureFlags;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();
    config([
        'services.midtrans.server_key' => 'SB-Mid-server-TOPUP-UJI',
        'services.midtrans.is_production' => false,
    ]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleEnum::Admin->value);
    app(FeatureFlags::class)->update([Feature::Wallet->value => true, Feature::Midtrans->value => true], $this->admin);

    $unit = Unit::factory()->create(['code' => 'MI']);
    $this->child = Student::factory()->create(['unit_id' => $unit->id]);
    $this->otherChild = Student::factory()->create(['unit_id' => $unit->id]);

    $this->guardianUser = User::factory()->create();
    $this->guardianUser->assignRole(RoleEnum::Guardian->value);
    $this->guardian = Guardian::factory()->create(['user_id' => $this->guardianUser->id]);
    $this->guardian->students()->attach($this->child->id, ['relation' => 'ibu', 'is_primary' => true]);

    Http::fake([
        'app.sandbox.midtrans.com/snap/v1/transactions' => Http::response(['token' => 'tok-topup', 'redirect_url' => 'https://app.sandbox.midtrans.com/r'], 201),
    ]);
});

test('halaman dompet menawarkan nominal cepat hanya saat pembayaran online aktif', function (): void {
    $this->actingAs($this->guardianUser)
        ->get(route('guardian.students.wallet.index', $this->child))
        ->assertInertia(fn (Assert $page) => $page
            ->where('topUp.presets', [20_000, 50_000, 100_000])
            ->where('topUp.min', 10_000)
            ->where('topUp.max', 1_000_000));

    app(FeatureFlags::class)->update([Feature::Midtrans->value => false], $this->admin);

    $this->actingAs($this->guardianUser)
        ->get(route('guardian.students.wallet.index', $this->child))
        ->assertInertia(fn (Assert $page) => $page->where('topUp', null));

    $this->actingAs($this->guardianUser)
        ->post(route('guardian.students.wallet.topup', $this->child), ['amount' => 50_000])
        ->assertNotFound();
});

test('wali membuat order isi saldo dan saldo belum bertambah sebelum webhook', function (): void {
    $this->actingAs($this->guardianUser)
        ->post(route('guardian.students.wallet.topup', $this->child), ['amount' => 50_000])
        ->assertRedirect();

    $order = PaymentOrder::query()->sole();
    expect($order->purpose)->toBe(PaymentOrderPurpose::WalletTopup)
        ->and($order->status)->toBe(PaymentOrderStatus::Pending)
        ->and($order->gross_amount)->toBe(50_000)
        ->and(WalletEntry::query()->count())->toBe(0);

    Http::assertSent(fn (Request $r): bool => $r['transaction_details']['gross_amount'] === 50_000
        && $r['item_details'][0]['id'] === 'wallet-topup');
});

test('nominal di luar batas ditolak dan anak lain 404', function (): void {
    $this->actingAs($this->guardianUser)
        ->post(route('guardian.students.wallet.topup', $this->child), ['amount' => 5_000])
        ->assertSessionHasErrors('amount');
    $this->actingAs($this->guardianUser)
        ->post(route('guardian.students.wallet.topup', $this->child), ['amount' => 1_500_000])
        ->assertSessionHasErrors('amount');
    $this->actingAs($this->guardianUser)
        ->post(route('guardian.students.wallet.topup', $this->otherChild), ['amount' => 50_000])
        ->assertNotFound();

    expect(PaymentOrder::query()->count())->toBe(0);
});

test('saldo bertambah sekali hanya lewat webhook settlement (§6 no. 11)', function (): void {
    $this->actingAs($this->guardianUser)
        ->post(route('guardian.students.wallet.topup', $this->child), ['amount' => 20_000]);
    $order = PaymentOrder::query()->sole();

    Http::fake([
        'api.sandbox.midtrans.com/*' => Http::response([
            'status_code' => '200', 'transaction_status' => 'settlement', 'gross_amount' => '20000.00', 'transaction_id' => 't',
        ]),
    ]);

    $body = [
        'order_id' => $order->order_id,
        'status_code' => '200',
        'gross_amount' => '20000.00',
        'transaction_status' => 'settlement',
    ];
    $body['signature_key'] = hash('sha512', $order->order_id.'200'.'20000.00'.'SB-Mid-server-TOPUP-UJI');

    $this->postJson(route('webhooks.midtrans'), $body)->assertOk();
    $this->postJson(route('webhooks.midtrans'), $body)->assertOk();

    expect(Wallet::query()->where('student_id', $this->child->id)->sole()->balance)->toBe(20_000)
        ->and(WalletEntry::query()->count())->toBe(1);
});
