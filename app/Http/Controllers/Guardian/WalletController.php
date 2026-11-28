<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guardian;

use App\Enums\Feature;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guardian\UpdateWalletLimitRequest;
use App\Models\CanteenVoucher;
use App\Models\Student;
use App\Models\User;
use App\Models\WalletEntry;
use App\Services\Features\FeatureFlags;
use App\Services\Wallet\WalletService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dompet kantin anak di portal wali murid (T-806).
 */
class WalletController extends Controller
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly FeatureFlags $features,
    ) {}

    public function index(Request $request, Student $student): Response
    {
        $wallet = $this->walletService->ensureWallet($student);
        $spentToday = $this->walletService->spentTodayWita($wallet);
        $todayDate = CarbonImmutable::now('Asia/Makassar')->toDateString();

        $vouchers = CanteenVoucher::query()
            ->where('student_id', $student->id)
            ->where('remaining', '>', 0)
            ->whereDate('expires_on', '>=', $todayDate)
            ->orderBy('expires_on')
            ->get()
            ->map(fn (CanteenVoucher $v): array => [
                'id' => $v->id,
                'amount' => $v->amount,
                'remaining' => $v->remaining,
                'expires_on' => $v->expires_on->toDateString(),
            ])
            ->values()
            ->all();

        $entries = WalletEntry::query()
            ->where('wallet_id', $wallet->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (WalletEntry $entry): array => [
                'id' => $entry->id,
                'type' => $entry->type->value,
                'type_label' => $entry->type->label(),
                'amount' => $entry->amount,
                'balance_after' => $entry->balance_after,
                'description' => $entry->description,
                'created_at' => $entry->created_at->toIso8601String(),
            ]);

        return Inertia::render('guardian/wallet', [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'nis' => $student->nis,
            ],
            'wallet' => [
                'balance' => $wallet->balance,
                'daily_limit' => $wallet->daily_limit,
                'spent_today' => $spentToday,
                'is_frozen' => $wallet->is_frozen,
                'max_daily_limit' => (int) config('sekolah.wallet.max_daily_limit', 100_000),
            ],
            'vouchers' => $vouchers,
            'entries' => $entries,
            // Isi saldo online (T-805): tampil hanya bila pembayaran Midtrans aktif.
            'topUp' => $this->features->enabled(Feature::Midtrans) ? [
                'min' => (int) config('sekolah.wallet.min_top_up', 10_000),
                'max' => (int) config('sekolah.wallet.max_top_up', 1_000_000),
                'presets' => [20_000, 50_000, 100_000],
            ] : null,
        ]);
    }

    public function updateLimit(UpdateWalletLimitRequest $request, Student $student): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $this->walletService->updateDailyLimit($student, $request->integer('daily_limit'), $user);

        return back()->with('success', 'Limit jajan harian berhasil diperbarui.');
    }

    public function toggleFreeze(Request $request, Student $student): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'is_frozen' => ['required', 'boolean'],
        ]);

        $frozen = (bool) $validated['is_frozen'];
        $this->walletService->setFrozen($student, $frozen, $user);

        return back()->with('success', $frozen ? 'Dompet berhasil dibekukan.' : 'Dompet berhasil diaktifkan kembali.');
    }
}
