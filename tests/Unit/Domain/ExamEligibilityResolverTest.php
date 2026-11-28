<?php

declare(strict_types=1);

use App\Domain\Exam\ExamEligibilityResolver;
use App\Enums\ExamRequirementScope;
use App\Enums\InvoiceStatus;
use Carbon\CarbonImmutable;

function makeReqRule(int $feeTypeId, ExamRequirementScope $scope, ?string $periodUntil = null): array
{
    return [
        'fee_type_id' => $feeTypeId,
        'invoice_scope' => $scope,
        'period_until' => $periodUntil !== null ? CarbonImmutable::parse($periodUntil) : null,
    ];
}

function makeInvSnapshot(
    int $id,
    int $feeTypeId,
    int $amount,
    int $paidTotal,
    InvoiceStatus $status,
    ?string $period = null,
    int $adjustmentTotal = 0,
): array {
    return [
        'id' => $id,
        'fee_type_id' => $feeTypeId,
        'amount' => $amount,
        'adjustment_total' => $adjustmentTotal,
        'paid_total' => $paidTotal,
        'status' => $status,
        'period' => $period !== null ? CarbonImmutable::parse($period) : null,
    ];
}

test('siswa layak bila semua tagihan sesuai syarat sudah lunas', function (): void {
    $resolver = new ExamEligibilityResolver;

    $result = $resolver->evaluate(
        [makeReqRule(1, ExamRequirementScope::UpToPeriod, '2026-11-01')],
        [makeInvSnapshot(10, 1, 150_000, 150_000, InvoiceStatus::Paid, '2026-11-01')],
    );

    expect($result['eligible'])->toBeTrue()
        ->and($result['status'])->toBe('eligible')
        ->and($result['blocking_invoice_ids'])->toBeEmpty();
});

test('cicilan sebagian membuat siswa belum layak', function (): void {
    $resolver = new ExamEligibilityResolver;

    $result = $resolver->evaluate(
        [makeReqRule(1, ExamRequirementScope::UpToPeriod, '2026-11-01')],
        [makeInvSnapshot(10, 1, 150_000, 100_000, InvoiceStatus::Partial, '2026-11-01')],
    );

    expect($result['eligible'])->toBeFalse()
        ->and($result['status'])->toBe('ineligible')
        ->and($result['blocking_invoice_ids'])->toBe([10]);
});

test('tagihan di luar jenis syarat atau melewati batas periode diabaikan', function (): void {
    $resolver = new ExamEligibilityResolver;

    $result = $resolver->evaluate(
        [makeReqRule(1, ExamRequirementScope::UpToPeriod, '2026-11-01')],
        [
            makeInvSnapshot(10, 1, 150_000, 150_000, InvoiceStatus::Paid, '2026-11-01'),
            makeInvSnapshot(11, 1, 150_000, 0, InvoiceStatus::Unpaid, '2026-12-01'),
            makeInvSnapshot(12, 2, 500_000, 0, InvoiceStatus::Unpaid, '2026-11-01'),
        ],
    );

    expect($result['eligible'])->toBeTrue()
        ->and($result['blocking_invoice_ids'])->toBeEmpty();
});

test('tagihan dibatalkan diabaikan oleh resolver', function (): void {
    $resolver = new ExamEligibilityResolver;

    $result = $resolver->evaluate(
        [makeReqRule(1, ExamRequirementScope::AllOpen)],
        [makeInvSnapshot(10, 1, 150_000, 0, InvoiceStatus::Cancelled, '2026-11-01')],
    );

    expect($result['eligible'])->toBeTrue()
        ->and($result['blocking_invoice_ids'])->toBeEmpty();
});

test('dispensasi aktif melayakkan siswa meski ada tagihan belum lunas', function (): void {
    $resolver = new ExamEligibilityResolver;

    $result = $resolver->evaluate(
        [makeReqRule(1, ExamRequirementScope::AllOpen)],
        [makeInvSnapshot(10, 1, 150_000, 0, InvoiceStatus::Unpaid, '2026-11-01')],
        hasActiveDispensation: true,
    );

    expect($result['eligible'])->toBeTrue()
        ->and($result['status'])->toBe('dispensed')
        ->and($result['blocking_invoice_ids'])->toBe([10]);
});
