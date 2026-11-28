<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletEntry;
use App\Notifications\WalletBalanceMismatchNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Verifikasi harian cache saldo dompet terhadap jumlah entri ledger (T-802, ADR-006).
 * Melaporkan selisih ke log dan admin tanpa koreksi otomatis.
 */
class VerifyWalletBalances extends Command
{
    protected $signature = 'wallet:verify-balances';

    protected $description = 'Bandingkan cache saldo dompet dengan jumlah ledger dan laporkan selisih tanpa koreksi otomatis.';

    public function handle(): int
    {
        $ledgerSums = WalletEntry::query()
            ->selectRaw('wallet_id, coalesce(sum(amount), 0) as total')
            ->groupBy('wallet_id')
            ->pluck('total', 'wallet_id');

        $mismatches = [];

        Wallet::query()
            ->orderBy('id')
            ->chunkById(500, function ($wallets) use ($ledgerSums, &$mismatches): void {
                foreach ($wallets as $wallet) {
                    $ledgerSum = (int) ($ledgerSums[$wallet->id] ?? 0);
                    if ($wallet->balance !== $ledgerSum) {
                        $mismatches[] = [
                            'wallet_id' => $wallet->id,
                            'student_id' => $wallet->student_id,
                            'cached_balance' => $wallet->balance,
                            'ledger_sum' => $ledgerSum,
                        ];
                    }
                }
            });

        if ($mismatches === []) {
            $this->info('Semua saldo dompet cocok dengan ledger.');

            return self::SUCCESS;
        }

        Log::error('Wallet balance mismatch detected', ['count' => count($mismatches), 'mismatches' => $mismatches]);

        $admins = User::role(Role::Admin->value)->where('is_active', true)->get();
        if ($admins->isNotEmpty()) {
            Notification::send($admins, new WalletBalanceMismatchNotification($mismatches));
        }

        $this->error(count($mismatches).' dompet mengalami selisih saldo.');

        return self::FAILURE;
    }
}
