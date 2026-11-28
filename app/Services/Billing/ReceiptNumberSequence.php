<?php

declare(strict_types=1);

namespace App\Services\Billing;

use Illuminate\Support\Facades\DB;

/**
 * Nomor kuitansi berurutan per tahun (`KW-2026-000123`), aman dari balapan (T-605).
 * Dipakai kasir (ManualPaymentService) dan pembayaran Midtrans; wajib dipanggil di dalam transaksi.
 */
final class ReceiptNumberSequence
{
    public function next(int $year): string
    {
        DB::table('receipt_sequences')->insertOrIgnore([
            'year' => $year,
            'last_number' => 0,
        ]);

        /** @var object{last_number: int}|null $row */
        $row = DB::table('receipt_sequences')
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        $next = ($row !== null ? (int) $row->last_number : 0) + 1;

        DB::table('receipt_sequences')
            ->where('year', $year)
            ->update(['last_number' => $next]);

        return sprintf('KW-%d-%06d', $year, $next);
    }
}
