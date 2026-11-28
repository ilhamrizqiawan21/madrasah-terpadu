<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Pilihan periode laporan/jurnal (hari ini, kemarin, minggu ini, … rentang tanggal) dalam zona WITA.
 * Dipakai bersama halaman keuangan agar label dan batasnya seragam.
 */
final readonly class PeriodRange
{
    public const array OPTIONS = [
        'today' => 'Hari ini',
        'yesterday' => 'Kemarin',
        'this_week' => 'Minggu ini',
        'this_month' => 'Bulan ini',
        'last_month' => 'Bulan lalu',
        'this_year' => 'Tahun ini',
        'all' => 'Semua waktu',
        'custom' => 'Rentang tanggal',
    ];

    private function __construct(
        public string $period,
        public string $label,
        public ?CarbonImmutable $start,
        public ?CarbonImmutable $end,
        public ?string $fromInput,
        public ?string $toInput,
    ) {}

    public static function fromRequest(Request $request, string $default = 'this_month'): self
    {
        $tz = AppTimezone::name();
        $now = CarbonImmutable::now($tz);
        $requested = $request->string('period')->toString();
        $period = array_key_exists($requested, self::OPTIONS) ? $requested : $default;
        $from = $request->date('from')?->toDateString();
        $to = $request->date('to')?->toDateString();

        [$start, $end] = match ($period) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            'yesterday' => [$now->subDay()->startOfDay(), $now->subDay()->endOfDay()],
            'this_week' => [$now->startOfWeek(CarbonImmutable::MONDAY), $now->endOfDay()],
            'this_month' => [$now->startOfMonth(), $now->endOfDay()],
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            'this_year' => [$now->startOfYear(), $now->endOfDay()],
            'custom' => [
                $from !== null ? CarbonImmutable::parse($from, $tz)->startOfDay() : null,
                $to !== null ? CarbonImmutable::parse($to, $tz)->endOfDay() : null,
            ],
            default => [null, null],
        };

        $label = match (true) {
            $period === 'all' => 'Semua waktu',
            $period === 'custom' => 'Rentang '.($start?->format('d/m/Y') ?? '…').' – '.($end?->format('d/m/Y') ?? '…'),
            default => self::OPTIONS[$period].' ('.$start?->format('d/m/Y').($start !== null && $end !== null && $start->isSameDay($end) ? '' : ' – '.$end?->format('d/m/Y')).')',
        };

        return new self($period, $label, $start, $end, $from, $to);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (string $k, string $v): array => ['value' => $k, 'label' => $v], array_keys(self::OPTIONS), self::OPTIONS);
    }

    /**
     * @return array{period: string, from: string|null, to: string|null}
     */
    public function toFilters(): array
    {
        return ['period' => $this->period, 'from' => $this->fromInput, 'to' => $this->toInput];
    }
}
