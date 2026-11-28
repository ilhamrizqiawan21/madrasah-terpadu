<?php

declare(strict_types=1);

use App\Enums\WalletEntryType;
use App\Exceptions\Wallet\DailyLimitExceededException;
use App\Exceptions\Wallet\FrozenWalletException;
use App\Exceptions\Wallet\InsufficientBalanceException;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletEntry;
use App\Services\Wallet\WalletService;
use Carbon\CarbonImmutable;

test('saldo dompet selalu sama dengan jumlah entri ledger untuk topUp, purchase, reverse, dan adjust', function (): void {
    $unit = Unit::factory()->create();
    $student = Student::factory()->for($unit)->create();
    $actor = User::factory()->create();
    $service = app(WalletService::class);

    $service->topUp($student, 50_000, 'topup-1', $actor);
    $purchaseEntry = $service->purchase($student, 12_000, 'purchase-1', $actor);
    $service->reverse($purchaseEntry, 'rev-1', $actor, 'Salah nominal');
    $service->adjust($student, -5_000, 'adj-1', $actor, 'Koreksi');

    $wallet = Wallet::query()->where('student_id', $student->id)->firstOrFail();
    $ledgerSum = (int) WalletEntry::query()->where('wallet_id', $wallet->id)->sum('amount');

    expect($wallet->balance)->toBe(45_000)
        ->and($ledgerSum)->toBe(45_000)
        ->and(WalletEntry::query()->where('wallet_id', $wallet->id)->count())->toBe(4);
});

test('idempotency_key mencegah entri ganda pada pemanggilan ulang', function (): void {
    $unit = Unit::factory()->create();
    $student = Student::factory()->for($unit)->create();
    $service = app(WalletService::class);

    $e1 = $service->topUp($student, 30_000, 'same-key-1');
    $e2 = $service->topUp($student, 30_000, 'same-key-1');

    $wallet = Wallet::query()->where('student_id', $student->id)->firstOrFail();

    expect($e1->id)->toBe($e2->id)
        ->and($wallet->balance)->toBe(30_000);
});

test('pembelian ditolak bila saldo kurang, melewati batas harian WITA, atau dompet beku', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-28 10:00', 'Asia/Makassar'));
    $unit = Unit::factory()->create();
    $student = Student::factory()->for($unit)->create();
    $service = app(WalletService::class);

    $service->topUp($student, 25_000, 'topup-guard');

    // Saldo kurang
    expect(fn () => $service->purchase($student, 30_000, 'buy-too-much'))
        ->toThrow(InsufficientBalanceException::class);

    // Batas harian default 20.000
    $service->purchase($student, 15_000, 'buy-1');
    expect(fn () => $service->purchase($student, 6_000, 'buy-over-limit'))
        ->toThrow(DailyLimitExceededException::class);

    // Dompet beku
    $wallet = Wallet::query()->where('student_id', $student->id)->firstOrFail();
    $wallet->forceFill(['is_frozen' => true])->save();

    expect(fn () => $service->purchase($student, 2_000, 'buy-frozen'))
        ->toThrow(FrozenWalletException::class);
});

test('pembelian berurutan dalam transaksi tidak pernah membuat saldo negatif', function (): void {
    $unit = Unit::factory()->create();
    $student = Student::factory()->for($unit)->create();
    $service = app(WalletService::class);

    $service->topUp($student, 15_000, 'topup-mysql');
    $service->purchase($student, 10_000, 'buy-mysql-1');

    expect(fn () => $service->purchase($student, 10_000, 'buy-mysql-2'))
        ->toThrow(InsufficientBalanceException::class);

    $wallet = Wallet::query()->where('student_id', $student->id)->firstOrFail();
    expect($wallet->balance)->toBe(5_000)
        ->and($wallet->entries()->where('type', WalletEntryType::Purchase)->count())->toBe(1);
})->group('mysql');
