<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentOrderPurpose;
use App\Enums\PaymentOrderStatus;
use App\Enums\Role as RoleEnum;
use App\Models\FeeType;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\PaymentOrder;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Features\FeatureFlags;
use App\Services\Payments\PaymentOrderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    config([
        'services.midtrans.server_key' => 'SB-Mid-server-ORDER-UJI',
        'services.midtrans.client_key' => 'SB-Mid-client-ORDER-UJI',
        'services.midtrans.is_production' => false,
    ]);

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    app(FeatureFlags::class)->update([
        Feature::Billing->value => true,
        Feature::Midtrans->value => true,
        Feature::Wallet->value => true,
    ], $admin);

    $unit = Unit::factory()->create(['code' => 'MI']);
    $this->child = Student::factory()->create(['unit_id' => $unit->id, 'name' => 'Ahmad Fauzi']);
    $this->otherChild = Student::factory()->create(['unit_id' => $unit->id]);

    $this->guardianUser = User::factory()->create();
    $this->guardianUser->assignRole(RoleEnum::Guardian->value);
    $this->guardian = Guardian::factory()->create(['user_id' => $this->guardianUser->id, 'name' => 'Bapak Fauzi']);
    $this->guardian->students()->attach($this->child->id, ['relation' => 'ayah', 'is_primary' => true]);

    $feeType = FeeType::factory()->create(['unit_id' => $unit->id]);
    $this->spp = Invoice::factory()->create([
        'student_id' => $this->child->id,
        'fee_type_id' => $feeType->id,
        'description' => 'SPP November 2026',
        'amount' => 150_000,
        'status' => InvoiceStatus::Unpaid,
    ]);
    $this->kegiatan = Invoice::factory()->create([
        'student_id' => $this->child->id,
        'fee_type_id' => $feeType->id,
        'description' => 'Kegiatan semester ganjil',
        'period' => '2026-07-01',
        'amount' => 50_000,
        'status' => InvoiceStatus::Unpaid,
    ]);
    $this->otherInvoice = Invoice::factory()->create([
        'student_id' => $this->otherChild->id,
        'fee_type_id' => $feeType->id,
        'amount' => 75_000,
        'status' => InvoiceStatus::Unpaid,
    ]);
});

function fakeSnapOk(): void
{
    Http::fake([
        'app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
            'token' => 'snap-token-uji',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/snap-token-uji',
        ], 201),
    ]);
}

test('tamu diarahkan ke login dan staf ditolak saat membuat order', function (): void {
    fakeSnapOk();

    $this->post(route('guardian.students.payments.store', $this->child), ['invoice_ids' => [$this->spp->id]])
        ->assertRedirect(route('login'));

    $staff = User::factory()->create();
    $staff->assignRole(RoleEnum::Treasurer->value);
    $this->actingAs($staff)
        ->post(route('guardian.students.payments.store', $this->child), ['invoice_ids' => [$this->spp->id]])
        ->assertForbidden();

    expect(PaymentOrder::query()->count())->toBe(0);
});

test('rute pembayaran online 404 saat fitur midtrans nonaktif', function (): void {
    fakeSnapOk();
    $admin = User::role(RoleEnum::Admin->value)->firstOrFail();
    app(FeatureFlags::class)->update([Feature::Midtrans->value => false], $admin);

    $this->actingAs($this->guardianUser)
        ->post(route('guardian.students.payments.store', $this->child), ['invoice_ids' => [$this->spp->id]])
        ->assertNotFound();
});

test('wali membuat order dengan nominal dari database dan gross_amount palsu dari browser diabaikan', function (): void {
    fakeSnapOk();

    $response = $this->actingAs($this->guardianUser)
        ->post(route('guardian.students.payments.store', $this->child), [
            'invoice_ids' => [$this->spp->id, $this->kegiatan->id],
            'gross_amount' => 1000,
        ]);

    $order = PaymentOrder::query()->sole();
    $response->assertRedirect(route('guardian.payments.show', $order));

    expect($order->purpose)->toBe(PaymentOrderPurpose::Invoices)
        ->and($order->status)->toBe(PaymentOrderStatus::Pending)
        ->and($order->gross_amount)->toBe(200_000)
        ->and($order->order_id)->toStartWith('INV-')
        ->and($order->guardian_id)->toBe($this->guardian->id)
        ->and($order->snap_token)->toBe('snap-token-uji')
        ->and($order->items()->count())->toBe(2);

    Http::assertSent(function (Request $request) use ($order): bool {
        $items = collect($request['item_details']);

        return $request['transaction_details']['order_id'] === $order->order_id
            && $request['transaction_details']['gross_amount'] === 200_000
            && $items->sum(fn (array $i): int => $i['price'] * $i['quantity']) === 200_000
            && $items->every(fn (array $i): bool => mb_strlen($i['name']) <= 50)
            && $request['customer_details']['first_name'] === 'Bapak Fauzi'
            && str_ends_with($request['callbacks']['finish'], "/wali/pembayaran/{$order->order_id}/selesai");
    });

    $this->assertDatabaseHas('audit_logs', ['action' => 'payment_order.created', 'subject_id' => $order->id]);
});

test('wali tidak bisa membayar tagihan anak lain (404)', function (): void {
    fakeSnapOk();

    $this->actingAs($this->guardianUser)
        ->post(route('guardian.students.payments.store', $this->otherChild), ['invoice_ids' => [$this->otherInvoice->id]])
        ->assertNotFound();

    // Tagihan anak lain diselipkan lewat rute anak sendiri.
    $this->actingAs($this->guardianUser)
        ->post(route('guardian.students.payments.store', $this->child), ['invoice_ids' => [$this->spp->id, $this->otherInvoice->id]])
        ->assertNotFound();

    expect(PaymentOrder::query()->count())->toBe(0);
    Http::assertNothingSent();
});

test('tagihan dalam order pending tidak bisa diorder lagi sampai order kedaluwarsa', function (): void {
    fakeSnapOk();

    $this->actingAs($this->guardianUser)
        ->post(route('guardian.students.payments.store', $this->child), ['invoice_ids' => [$this->spp->id]])
        ->assertRedirect();

    $this->actingAs($this->guardianUser)
        ->from(route('guardian.billing'))
        ->post(route('guardian.students.payments.store', $this->child), ['invoice_ids' => [$this->spp->id]])
        ->assertRedirect(route('guardian.billing'))
        ->assertSessionHasErrors('invoice_ids');

    expect(PaymentOrder::query()->count())->toBe(1);

    $this->travel(25)->hours();

    $this->actingAs($this->guardianUser)
        ->post(route('guardian.students.payments.store', $this->child), ['invoice_ids' => [$this->spp->id]])
        ->assertRedirect();

    expect(PaymentOrder::query()->count())->toBe(2);
})->group('mysql');

test('tagihan yang sudah dicicil tidak bisa dibayar online', function (): void {
    fakeSnapOk();
    $this->spp->forceFill(['paid_total' => 50_000, 'status' => InvoiceStatus::Partial])->save();

    $this->actingAs($this->guardianUser)
        ->post(route('guardian.students.payments.store', $this->child), ['invoice_ids' => [$this->spp->id]])
        ->assertSessionHasErrors('invoice_ids');

    expect(PaymentOrder::query()->count())->toBe(0);
});

test('Snap gagal menandai order gagal sehingga tagihan bisa dicoba lagi', function (): void {
    Http::fakeSequence('app.sandbox.midtrans.com/*')
        ->push(['error_messages' => ['server busy']], 503)
        ->push(['error_messages' => ['server busy']], 503)
        ->push(['error_messages' => ['server busy']], 503)
        ->push(['token' => 'snap-token-ulang', 'redirect_url' => 'https://app.sandbox.midtrans.com/r'], 201);

    $this->actingAs($this->guardianUser)
        ->post(route('guardian.students.payments.store', $this->child), ['invoice_ids' => [$this->spp->id]])
        ->assertSessionHasErrors('payment');

    expect(PaymentOrder::query()->sole()->status)->toBe(PaymentOrderStatus::Failed);

    $this->actingAs($this->guardianUser)
        ->post(route('guardian.students.payments.store', $this->child), ['invoice_ids' => [$this->spp->id]])
        ->assertRedirect();

    expect(PaymentOrder::query()->where('status', PaymentOrderStatus::Pending->value)->count())->toBe(1);
});

test('pembuatan order dibatasi 10 per menit per wali', function (): void {
    fakeSnapOk();

    foreach (range(1, 10) as $_) {
        $this->actingAs($this->guardianUser)
            ->post(route('guardian.students.payments.store', $this->child), ['invoice_ids' => [999_999]]);
    }

    $this->actingAs($this->guardianUser)
        ->post(route('guardian.students.payments.store', $this->child), ['invoice_ids' => [$this->spp->id]])
        ->assertTooManyRequests();
});

test('halaman konfirmasi menampilkan token Snap hanya untuk order milik wali', function (): void {
    fakeSnapOk();
    $order = app(PaymentOrderService::class)->createForInvoices($this->guardian, $this->child, [$this->spp->id]);

    $this->actingAs($this->guardianUser)
        ->get(route('guardian.payments.show', $order))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('guardian/payment')
            ->where('view', 'confirm')
            ->where('order.gross_amount', 150_000)
            ->where('snap.token', 'snap-token-uji')
            ->where('snap.client_key', 'SB-Mid-client-ORDER-UJI')
            ->where('snap.script_url', 'https://app.sandbox.midtrans.com/snap/snap.js'));

    $this->actingAs($this->guardianUser)
        ->get(route('guardian.payments.finish', $order))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('view', 'status')->where('snap', null));

    $otherUser = User::factory()->create();
    $otherUser->assignRole(RoleEnum::Guardian->value);
    $otherGuardian = Guardian::factory()->create(['user_id' => $otherUser->id]);
    $otherGuardian->students()->attach($this->otherChild->id, ['relation' => 'ibu', 'is_primary' => true]);

    $this->actingAs($otherUser)->get(route('guardian.payments.show', $order))->assertNotFound();
    $this->actingAs($otherUser)->get(route('guardian.payments.finish', $order))->assertNotFound();
});

test('portal tagihan menandai tagihan yang sedang menunggu pembayaran online', function (): void {
    fakeSnapOk();
    $order = app(PaymentOrderService::class)->createForInvoices($this->guardian, $this->child, [$this->spp->id]);

    $this->actingAs($this->guardianUser)
        ->get(route('guardian.billing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('children.0.open_invoices', fn ($invoices): bool => collect($invoices)
                ->firstWhere('id', $this->spp->id)['pending_order_id'] === $order->order_id
                && collect($invoices)->firstWhere('id', $this->kegiatan->id)['pending_order_id'] === null));
});

test('order top-up memvalidasi rentang nominal dan berawalan TOP-', function (): void {
    fakeSnapOk();
    $service = app(PaymentOrderService::class);

    expect(fn () => $service->createForTopUp($this->guardian, $this->child, 5_000))
        ->toThrow(ValidationException::class);
    expect(fn () => $service->createForTopUp($this->guardian, $this->child, 2_000_000))
        ->toThrow(ValidationException::class);

    $order = $service->createForTopUp($this->guardian, $this->child, 50_000);

    expect($order->order_id)->toStartWith('TOP-')
        ->and($order->purpose)->toBe(PaymentOrderPurpose::WalletTopup)
        ->and($order->gross_amount)->toBe(50_000)
        ->and($order->items()->sole()->invoice_id)->toBeNull();
});
