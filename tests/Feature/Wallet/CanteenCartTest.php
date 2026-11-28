<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\CanteenProduct;
use App\Models\CanteenTransaction;
use App\Models\ProductCategory;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\CanteenPurchaseMadeNotification;
use App\Services\Wallet\WalletService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->unit = Unit::factory()->create(['code' => 'MI']);
    $this->student = Student::factory()->for($this->unit)->create(['nickname' => 'Ahmad']);
    $this->cashier = User::factory()->create();
    $this->cashier->assignRole(RoleEnum::CanteenCashier->value);
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    app(WalletService::class)->topUp($this->student, 30_000, 'topup-cart', $admin);

    $category = ProductCategory::query()->create(['unit_id' => $this->unit->id, 'module' => 'canteen', 'name' => 'Makanan']);
    $this->rice = CanteenProduct::query()->create(['unit_id' => $this->unit->id, 'product_category_id' => $category->id, 'name' => 'Nasi goreng', 'price' => 8000, 'stock' => 3, 'is_active' => true]);
    $this->tea = CanteenProduct::query()->create(['unit_id' => $this->unit->id, 'product_category_id' => $category->id, 'name' => 'Es teh', 'price' => 3000, 'stock' => null, 'is_active' => true]);
});

function buy(object $test, array $payload)
{
    return $test->actingAs($test->cashier)->postJson(route('canteen.purchase'), [
        'student_id' => $test->student->id, 'client_uuid' => (string) Str::uuid(), ...$payload,
    ]);
}

test('total dihitung dari harga menu di server, stok berkurang, wali mendapat rincian, klik ganda tidak memotong stok dua kali', function (): void {
    Notification::fake();
    $uuid = (string) Str::uuid();
    $payload = [
        'student_id' => $this->student->id, 'client_uuid' => $uuid,
        'lines' => [['product_id' => $this->rice->id, 'qty' => 2], ['product_id' => $this->tea->id, 'qty' => 1]],
        'manual_amount' => 1000, 'manual_note' => 'Kerupuk',
        'total' => 100, // diabaikan pada mode keranjang
    ];

    $this->actingAs($this->cashier)->postJson(route('canteen.purchase'), $payload)
        ->assertOk()
        ->assertJsonPath('transaction.total', 20_000)
        ->assertJsonPath('receipt.balance_after', 10_000)
        ->assertJsonPath('receipt.items.2.name', 'Kerupuk');
    $this->actingAs($this->cashier)->postJson(route('canteen.purchase'), $payload)->assertOk();

    expect(CanteenTransaction::query()->count())->toBe(1)
        ->and($this->rice->fresh()->stock)->toBe(1)
        ->and(Wallet::query()->where('student_id', $this->student->id)->value('balance'))->toBe(10_000);

    Notification::assertSentTimes(CanteenPurchaseMadeNotification::class, 0); // belum ada wali terhubung
    expect((new CanteenPurchaseMadeNotification(1, 'Ahmad', 20_000, 10_000, 'Nasi goreng ×2, Es teh ×1 dan 1 lainnya'))->body(new stdClass))
        ->toBe('Ananda Ahmad jajan Nasi goreng ×2, Es teh ×1 dan 1 lainnya seharga Rp 20.000 di Kantin. Sisa saldo Rp 10.000.');
});

test('stok kurang, menu nonaktif, dan saldo kurang ditolak tanpa mengubah stok', function (): void {
    buy($this, ['lines' => [['product_id' => $this->rice->id, 'qty' => 4]]])
        ->assertUnprocessable()->assertJsonPath('errors.lines.0', 'Stok Nasi goreng tinggal 3.');

    $this->tea->update(['is_active' => false]);
    buy($this, ['lines' => [['product_id' => $this->tea->id, 'qty' => 1]]])->assertUnprocessable();

    // 3 × 8.000 = 24.000 ≤ saldo 30.000 tetapi limit harian bawaan bisa lebih kecil; pakai saldo kurang yang pasti.
    Wallet::query()->where('student_id', $this->student->id)->update(['balance' => 5_000]);
    buy($this, ['lines' => [['product_id' => $this->rice->id, 'qty' => 1]]])->assertUnprocessable();

    expect($this->rice->fresh()->stock)->toBe(3)->and(CanteenTransaction::query()->count())->toBe(0);
});

test('koreksi transaksi mengembalikan stok menu dan layar kasir memuat menu aktif', function (): void {
    $response = buy($this, ['lines' => [['product_id' => $this->rice->id, 'qty' => 2]]])->assertOk();
    $tx = CanteenTransaction::query()->findOrFail($response->json('transaction.id'));
    expect($this->rice->fresh()->stock)->toBe(1);

    $this->actingAs($this->cashier)->postJson(route('canteen.reverse', $tx), ['reason' => 'Salah pilih menu'])->assertOk();
    expect($this->rice->fresh()->stock)->toBe(3);

    $this->tea->update(['is_active' => false]);
    $this->actingAs($this->cashier)->get(route('canteen.index'))
        ->assertInertia(fn (Assert $page) => $page->component('canteen/index')
            ->has('products', 1)->where('products.0.name', 'Nasi goreng')->where('units.0.code', 'MI')
            ->where('recentTransactions.0.summary', 'Nasi goreng ×2'));
});
