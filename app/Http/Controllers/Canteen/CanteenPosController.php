<?php

declare(strict_types=1);

namespace App\Http\Controllers\Canteen;

use App\Enums\Permission;
use App\Exceptions\Wallet\DailyLimitExceededException;
use App\Exceptions\Wallet\FrozenWalletException;
use App\Exceptions\Wallet\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Canteen\CanteenPurchaseRequest;
use App\Http\Requests\Canteen\LookupStudentCardRequest;
use App\Http\Requests\Canteen\ReverseCanteenTransactionRequest;
use App\Models\CanteenTransaction;
use App\Models\Student;
use App\Models\User;
use App\Services\Wallet\CanteenPosService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Layar POS kantin (kiosk) beserta scan kartu, transaksi belanja, dan koreksi (T-803).
 */
class CanteenPosController extends Controller
{
    public function __construct(
        private readonly CanteenPosService $pos,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $today = CarbonImmutable::now('Asia/Makassar');

        $recentTransactions = CanteenTransaction::query()
            ->with('student:id,nis,name')
            ->whereBetween('created_at', [$today->startOfDay()->utc(), $today->endOfDay()->utc()])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(15)
            ->get()
            ->map(fn (CanteenTransaction $tx): array => [
                'id' => $tx->id,
                'student_name' => $tx->student->name,
                'student_nis' => $tx->student->nis,
                'total' => $tx->total,
                'paid_from_vouchers' => $tx->paid_from_vouchers,
                'paid_from_wallet' => $tx->paid_from_wallet,
                'status' => $tx->status->value,
                'status_label' => $tx->status->label(),
                'created_at' => $tx->created_at->toIso8601String(),
                'can_reverse' => $tx->status->value === 'completed'
                    && ($user->can(Permission::WalletManage->value)
                        || ($tx->cashier_id === $user->id && now()->lessThanOrEqualTo($tx->created_at->addMinutes((int) config('sekolah.wallet.cashier_reversal_window_minutes', 15))))),
            ])
            ->values()
            ->all();

        return Inertia::render('canteen/index', [
            'recentTransactions' => $recentTransactions,
        ]);
    }

    public function lookup(LookupStudentCardRequest $request): JsonResponse
    {
        $data = $this->pos->lookupByToken($request->string('token')->toString());

        if ($data === null) {
            return response()->json([
                'message' => 'Kartu tidak dikenali atau siswa tidak aktif.',
            ], 404);
        }

        return response()->json($data);
    }

    public function purchase(CanteenPurchaseRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        /** @var array{student_id: int, total: int, client_uuid: string, items?: list<array{name: string, amount: int}>|null} $validated */
        $validated = $request->validated();
        $student = Student::query()->findOrFail((int) $validated['student_id']);

        try {
            $tx = $this->pos->purchase(
                cashier: $user,
                student: $student,
                total: (int) $validated['total'],
                clientUuid: $validated['client_uuid'],
                items: $validated['items'] ?? null,
            );
        } catch (FrozenWalletException|InsufficientBalanceException|DailyLimitExceededException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'transaction' => [
                'id' => $tx->id,
                'student_name' => $student->name,
                'student_nis' => $student->nis,
                'total' => $tx->total,
                'paid_from_vouchers' => $tx->paid_from_vouchers,
                'paid_from_wallet' => $tx->paid_from_wallet,
                'status' => $tx->status->value,
                'status_label' => $tx->status->label(),
                'created_at' => $tx->created_at->toIso8601String(),
                'can_reverse' => true,
            ],
        ]);
    }

    public function reverse(ReverseCanteenTransactionRequest $request, CanteenTransaction $transaction): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $tx = $this->pos->reverse($user, $transaction, $request->string('reason')->toString());

        return response()->json([
            'transaction' => [
                'id' => $tx->id,
                'status' => $tx->status->value,
                'status_label' => $tx->status->label(),
            ],
        ]);
    }
}
