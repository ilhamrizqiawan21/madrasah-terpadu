<?php

declare(strict_types=1);

use App\Domain\Payments\MidtransOutcome;
use App\Domain\Payments\MidtransStatusMapper;

it('memetakan status Midtrans sesuai tabel PAYMENTS §4', function (?string $status, ?string $fraud, MidtransOutcome $expected): void {
    expect(MidtransStatusMapper::map($status, $fraud))->toBe($expected);
})->with([
    'settlement' => ['settlement', null, MidtransOutcome::Paid],
    'settlement huruf besar' => ['SETTLEMENT', null, MidtransOutcome::Paid],
    'capture accept' => ['capture', 'accept', MidtransOutcome::Paid],
    'capture challenge' => ['capture', 'challenge', MidtransOutcome::Challenge],
    'capture tanpa fraud_status' => ['capture', null, MidtransOutcome::Challenge],
    'capture deny' => ['capture', 'deny', MidtransOutcome::Failed],
    'pending' => ['pending', null, MidtransOutcome::Pending],
    'expire' => ['expire', null, MidtransOutcome::Expired],
    'cancel' => ['cancel', null, MidtransOutcome::Failed],
    'deny' => ['deny', null, MidtransOutcome::Failed],
    'refund' => ['refund', null, MidtransOutcome::NeedsReview],
    'partial_refund' => ['partial_refund', null, MidtransOutcome::NeedsReview],
    'chargeback' => ['chargeback', null, MidtransOutcome::NeedsReview],
    'kosong' => [null, null, MidtransOutcome::Unknown],
    'tak dikenal' => ['aneh', null, MidtransOutcome::Unknown],
]);

it('mengubah gross_amount Midtrans menjadi rupiah utuh', function (mixed $input, ?int $expected): void {
    expect(MidtransStatusMapper::grossAmountToInt($input))->toBe($expected);
})->with([
    ['150000.00', 150000],
    ['150000', 150000],
    ['150000.0', 150000],
    [150000, 150000],
    ['150000.50', null],
    ['-1.00', null],
    ['abc', null],
    [null, null],
    [150000.0, null],
]);
