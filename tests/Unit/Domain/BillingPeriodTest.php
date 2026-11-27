<?php

declare(strict_types=1);

use App\Domain\Billing\BillingPeriod;
use App\Domain\Billing\InvoiceStatusResolver;
use App\Enums\FeeRecurrence;
use App\Enums\InvoiceStatus;
use Carbon\CarbonImmutable;

test('BillingPeriod expands monthly range inclusively and clamps due day to month end', function (): void {
    $periods = BillingPeriod::expandMonthly(
        CarbonImmutable::parse('2026-01-15'),
        CarbonImmutable::parse('2026-03-02'),
    );

    expect($periods)->toHaveCount(3)
        ->and($periods[0]->toDateString())->toBe('2026-01-01')
        ->and($periods[1]->toDateString())->toBe('2026-02-01')
        ->and($periods[2]->toDateString())->toBe('2026-03-01');

    expect(BillingPeriod::dueDateForPeriod($periods[1], 31, null)->toDateString())
        ->toBe('2026-02-28');

    expect(BillingPeriod::dueDateForPeriod($periods[0], 10, CarbonImmutable::parse('2026-04-05'))->toDateString())
        ->toBe('2026-04-05');
});

test('BillingPeriod formats Indonesian description for monthly and once fee types', function (): void {
    $period = CarbonImmutable::parse('2026-11-01');

    expect(BillingPeriod::descriptionFor('SPP MI', FeeRecurrence::Monthly, $period))
        ->toBe('SPP MI — November 2026');

    expect(BillingPeriod::descriptionFor('Uang Pangkal', FeeRecurrence::Once, $period, '2026/2027'))
        ->toBe('Uang Pangkal — 2026/2027');
});

test('InvoiceStatusResolver resolves unpaid, partial, paid, and cancelled accurately', function (): void {
    expect(InvoiceStatusResolver::remaining(150000, 50000, 100000))->toBe(0);
    expect(InvoiceStatusResolver::remaining(150000, 100000, 100000))->toBe(0);

    expect(InvoiceStatusResolver::resolve(150000, 0, 0))->toBe(InvoiceStatus::Unpaid);
    expect(InvoiceStatusResolver::resolve(150000, 0, 50000))->toBe(InvoiceStatus::Partial);
    expect(InvoiceStatusResolver::resolve(150000, 50000, 0))->toBe(InvoiceStatus::Unpaid);
    expect(InvoiceStatusResolver::resolve(150000, 50000, 100000))->toBe(InvoiceStatus::Paid);
    expect(InvoiceStatusResolver::resolve(150000, 150000, 0))->toBe(InvoiceStatus::Paid);
    expect(InvoiceStatusResolver::resolve(150000, 0, 0, true))->toBe(InvoiceStatus::Cancelled);
});
