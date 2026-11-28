<?php

declare(strict_types=1);

use App\Enums\CanteenTransactionStatus;
use App\Enums\Role as RoleEnum;
use App\Models\AcademicYear;
use App\Models\CanteenTransaction;
use App\Models\CanteenVoucher;
use App\Models\Guardian;
use App\Models\RewardOffer;
use App\Models\RewardRedemption;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\CanteenPurchaseMadeNotification;
use App\Services\Attendance\StudentCardService;
use App\Services\Wallet\CanteenPosService;
use App\Services\Wallet\WalletService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('POS kantin memotong voucher dulu sebelum saldo uang dan mengirim notifikasi ke wali', function (): void {
    Notification::fake();

    $unit = Unit::factory()->create();
    $student = Student::factory()->for($unit)->create();
    $cashier = User::factory()->create();
    $cashier->assignRole(RoleEnum::CanteenCashier->value);

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    app(WalletService::class)->topUp($student, 50_000, 'topup-pos-1', $admin);

    $offer = RewardOffer::query()->create([
        'unit_id' => $unit->id,
        'name' => 'Voucher Jajan 10rb',
        'cost_points' => 10,
        'voucher_amount' => 10_000,
        'voucher_valid_days' => 30,
        'is_active' => true,
    ]);

    $redemption = RewardRedemption::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => AcademicYear::factory()->active()->create()->id,
        'reward_offer_id' => $offer->id,
        'cost_points' => 10,
        'voucher_amount' => 10_000,
        'requested_by' => $admin->id,
        'status' => 'approved',
        'decided_by' => $admin->id,
        'decided_at' => now(),
    ]);

    $voucher = CanteenVoucher::query()->create([
        'student_id' => $student->id,
        'reward_redemption_id' => $redemption->id,
        'amount' => 10_000,
        'remaining' => 10_000,
        'expires_on' => now()->addDays(10)->toDateString(),
        'created_at' => now(),
    ]);

    $issued = app(StudentCardService::class)->issueQr($student, $admin);
    $lookup = app(CanteenPosService::class)->lookupByToken($issued['token']);
    expect($lookup)->not->toBeNull()
        ->and($lookup['vouchers']['total_remaining'])->toBe(10_000);

    $uuid = (string) Str::uuid();
    $this->actingAs($cashier)
        ->postJson(route('canteen.purchase'), [
            'student_id' => $student->id,
            'total' => 15_000,
            'client_uuid' => $uuid,
        ])
        ->assertOk()
        ->assertJsonPath('transaction.paid_from_vouchers', 10_000)
        ->assertJsonPath('transaction.paid_from_wallet', 5_000);

    // Klik ganda dengan client_uuid sama -> satu transaksi saja
    $this->actingAs($cashier)
        ->postJson(route('canteen.purchase'), [
            'student_id' => $student->id,
            'total' => 15_000,
            'client_uuid' => $uuid,
        ])
        ->assertOk();

    expect(CanteenTransaction::query()->where('client_uuid', $uuid)->count())->toBe(1)
        ->and($voucher->fresh()?->remaining)->toBe(0)
        ->and(Wallet::query()->where('student_id', $student->id)->value('balance'))->toBe(45_000);

    // Pastikan tidak error bila ada wali terhubung
    $guardianUser = User::factory()->create();
    $guardianUser->assignRole(RoleEnum::Guardian->value);
    $guardian = Guardian::factory()->for($guardianUser)->create();
    $guardian->students()->attach($student->id, ['relation' => 'ibu', 'is_primary' => true]);

    $uuid2 = (string) Str::uuid();
    $this->actingAs($cashier)
        ->postJson(route('canteen.purchase'), [
            'student_id' => $student->id,
            'total' => 5_000,
            'client_uuid' => $uuid2,
        ])
        ->assertOk();

    Notification::assertSentTo($guardianUser, CanteenPurchaseMadeNotification::class);
})->group('mysql');

test('koreksi transaksi POS oleh kasir dibatasi 15 menit dan mengembalikan saldo serta limit harian WITA', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-28 09:00', 'Asia/Makassar'));

    $unit = Unit::factory()->create();
    $student = Student::factory()->for($unit)->create();
    $cashier1 = User::factory()->create();
    $cashier1->assignRole(RoleEnum::CanteenCashier->value);
    $cashier2 = User::factory()->create();
    $cashier2->assignRole(RoleEnum::CanteenCashier->value);
    $treasurer = User::factory()->create();
    $treasurer->assignRole(RoleEnum::Treasurer->value);

    $walletService = app(WalletService::class);
    $walletService->topUp($student, 50_000, 'topup-rev-test');

    $pos = app(CanteenPosService::class);
    $tx1 = $pos->purchase($cashier1, $student, 18_000, (string) Str::uuid());

    // Kasir lain tidak bisa membalikkan
    $this->actingAs($cashier2)
        ->postJson(route('canteen.reverse', $tx1), ['reason' => 'Salah'])
        ->assertForbidden();

    // Lewat 16 menit -> kasir1 ditolak 403, bendahara tetap bisa
    $this->travel(16)->minutes();

    $this->actingAs($cashier1)
        ->postJson(route('canteen.reverse', $tx1), ['reason' => 'Salah'])
        ->assertForbidden();

    $this->actingAs($treasurer)
        ->postJson(route('canteen.reverse', $tx1), ['reason' => 'Salah input nominal'])
        ->assertOk();

    $wallet = Wallet::query()->where('student_id', $student->id)->firstOrFail();
    expect($tx1->fresh()?->status)->toBe(CanteenTransactionStatus::Reversed)
        ->and($wallet->balance)->toBe(50_000)
        ->and($walletService->spentTodayWita($wallet))->toBe(0);
});
