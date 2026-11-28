<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\GuardianRelation;
use App\Enums\Role as RoleEnum;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletEntry;
use App\Notifications\WalletTopupCancelledNotification;
use App\Services\Features\FeatureFlags;
use App\Services\Wallet\WalletService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->treasurer = User::factory()->create();
    $this->treasurer->assignRole(RoleEnum::Treasurer->value);
    app(FeatureFlags::class)->update([Feature::Wallet->value => true], $this->treasurer);
    $this->unit = Unit::factory()->create(['code' => 'MI']);
    $this->student = Student::factory()->for($this->unit)->create(['name' => 'Ahmad Fauzi']);
});

function cashTopUp(object $test, int $amount): WalletEntry
{
    $test->actingAs($test->treasurer)->post(route('admin.wallets.topup'), [
        'student_id' => $test->student->id, 'amount' => $amount, 'client_uuid' => (string) Str::uuid(),
    ])->assertSessionHasNoErrors();

    return WalletEntry::query()->latest('id')->firstOrFail();
}

test('riwayat memisahkan tunai dan online, pembatalan tunai mengurangi saldo sekali dan memberi tahu wali', function (): void {
    Notification::fake();
    $guardianUser = User::factory()->create();
    $guardianUser->assignRole(RoleEnum::Guardian->value);
    $guardian = Guardian::factory()->create(['user_id' => $guardianUser->id]);
    $this->student->guardians()->attach($guardian->id, ['relation' => GuardianRelation::Father->value, 'is_primary' => true]);

    $cash = cashTopUp($this, 50_000);
    app(WalletService::class)->topUp($this->student, 20_000, 'midtrans:ORDER-1', null, 'Isi saldo online');

    $this->actingAs($this->treasurer)->get(route('admin.wallet-topups.index', ['period' => 'today']))
        ->assertInertia(fn (Assert $page) => $page->component('admin/wallets/topups')
            ->has('rows.data', 2)
            ->where('summary.cash', 50_000)->where('summary.online', 20_000)->where('summary.net', 70_000));

    $online = WalletEntry::query()->where('idempotency_key', 'midtrans:ORDER-1')->firstOrFail();
    $this->actingAs($this->treasurer)->post(route('admin.wallet-topups.cancel', $online), ['reason' => 'Coba'])->assertSessionHasErrors('entry');

    $this->actingAs($this->treasurer)->post(route('admin.wallet-topups.cancel', $cash), ['reason' => 'Salah pilih siswa'])->assertSessionHasNoErrors();
    $this->actingAs($this->treasurer)->post(route('admin.wallet-topups.cancel', $cash), ['reason' => 'Salah pilih siswa'])->assertSessionHasNoErrors();

    expect(Wallet::query()->where('student_id', $this->student->id)->value('balance'))->toBe(20_000);
    Notification::assertSentToTimes($guardianUser, WalletTopupCancelledNotification::class, 1);

    $this->actingAs($this->treasurer)->get(route('admin.wallet-topups.index', ['period' => 'all', 'method' => 'cash']))
        ->assertInertia(fn (Assert $page) => $page->has('rows.data', 1)
            ->where('rows.data.0.cancelled', true)->where('rows.data.0.cancel_reason', 'Salah pilih siswa')
            ->where('summary.cancelled', 50_000)->where('summary.net', 0));
});

test('isi saldo yang sudah terpakai tidak bisa dibatalkan dan ekspor rekap tersedia', function (): void {
    $cash = cashTopUp($this, 30_000);
    Wallet::query()->where('student_id', $this->student->id)->update(['balance' => 10_000]);

    $this->actingAs($this->treasurer)->post(route('admin.wallet-topups.cancel', $cash), ['reason' => 'Salah nominal'])
        ->assertSessionHasErrors('reason');
    expect(Wallet::query()->where('student_id', $this->student->id)->value('balance'))->toBe(10_000);

    $pdf = $this->actingAs($this->treasurer)->get(route('admin.wallet-topups.pdf', ['period' => 'all']))->assertOk();
    expect(substr((string) $pdf->getContent(), 0, 4))->toBe('%PDF');
    $this->actingAs($this->treasurer)->get(route('admin.wallet-topups.excel', ['period' => 'all']))->assertOk();
});

test('kasir kantin tidak bisa membuka riwayat isi saldo', function (): void {
    $cashier = User::factory()->create();
    $cashier->assignRole(RoleEnum::CanteenCashier->value);
    $this->actingAs($cashier)->get(route('admin.wallet-topups.index'))->assertForbidden();
});
