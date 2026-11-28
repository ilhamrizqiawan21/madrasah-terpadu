<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Payments\PaymentReconciliationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Rekonsiliasi harian order Midtrans (T-705, PAYMENTS §5). Bawaan: order yang dibuat kemarin (WITA).
 */
final class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile {date? : Tanggal WITA format YYYY-MM-DD, bawaan kemarin}';

    protected $description = 'Cocokkan order Midtrans pada satu tanggal dengan Get Status dan laporkan selisih ke bendahara.';

    public function handle(PaymentReconciliationService $service): int
    {
        $argument = $this->argument('date');

        try {
            $date = is_string($argument) && $argument !== ''
                ? CarbonImmutable::createFromFormat('!Y-m-d', $argument, 'Asia/Makassar')
                : CarbonImmutable::now('Asia/Makassar')->subDay()->startOfDay();
        } catch (InvalidArgumentException) {
            $date = null;
        }

        if (! $date instanceof CarbonImmutable) {
            $this->components->error('Tanggal harus berformat YYYY-MM-DD.');

            return self::FAILURE;
        }

        $summary = $service->reconcileDate($date);

        $this->components->info(sprintf(
            'Rekonsiliasi %s: %d order dicek, %d selisih, %d gagal dicek.',
            $date->toDateString(),
            $summary['checked'],
            count($summary['discrepancies']),
            count($summary['failed']),
        ));

        foreach ($summary['discrepancies'] as $row) {
            $this->line("  {$row['order_id']}: {$row['before']} → {$row['after']} ({$row['result']})");
        }

        return $summary['failed'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
