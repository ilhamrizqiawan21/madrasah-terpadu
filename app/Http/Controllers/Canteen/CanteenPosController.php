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
use App\Models\CanteenProduct;
use App\Models\CanteenTransaction;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Wallet\CanteenPosService;
use App\Support\AppTimezone;
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

        $products = CanteenProduct::query()
            ->with(['category:id,name', 'unit:id,code'])
            ->where('is_active', true)
            ->orderBy('unit_id')->orderBy('product_category_id')->orderBy('name')->orderBy('id')
            ->get()
            ->map(fn (CanteenProduct $p): array => [
                'id' => $p->id, 'unit_id' => $p->unit_id, 'category_id' => $p->product_category_id,
                'category' => $p->category->name, 'name' => $p->name, 'price' => $p->price, 'stock' => $p->stock,
            ])
            ->values()
            ->all();

        $recentTransactions = CanteenTransaction::query()
            ->with('student:id,nis,name')
            ->whereBetween('created_at', [$today->startOfDay()->setTimezone(AppTimezone::name()), $today->endOfDay()->setTimezone(AppTimezone::name())])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(15)
            ->get()
            ->map(fn (CanteenTransaction $tx): array => [
                'id' => $tx->id,
                'summary' => CanteenPosService::summarizeItems($tx->items),
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
            'products' => $products,
            'units' => Unit::query()->where('is_active', true)->whereIn('id', array_unique(array_column($products, 'unit_id')))
                ->orderBy('id')->get(['id', 'code', 'name'])
                ->map(fn (Unit $u): array => ['id' => $u->id, 'code' => $u->code, 'name' => $u->name])->values()->all(),
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

        $validated = $request->validated();
        $student = Student::query()->findOrFail((int) $validated['student_id']);
        $clientUuid = (string) $validated['client_uuid'];

        try {
            if (isset($validated['lines']) || isset($validated['manual_amount'])) {
                /** @var list<array{product_id: int|string, qty: int|string}> $rawLines */
                $rawLines = $validated['lines'] ?? [];
                $tx = $this->pos->purchaseCart(
                    cashier: $user,
                    student: $student,
                    lines: array_map(static fn (array $l): array => ['product_id' => (int) $l['product_id'], 'qty' => (int) $l['qty']], $rawLines),
                    manualAmount: (int) ($validated['manual_amount'] ?? 0),
                    manualNote: isset($validated['manual_note']) ? (string) $validated['manual_note'] : null,
                    clientUuid: $clientUuid,
                );
            } else {
                /** @var list<array{name: string, amount: int}>|null $items */
                $items = $validated['items'] ?? null;
                $tx = $this->pos->purchase(
                    cashier: $user,
                    student: $student,
                    total: (int) $validated['total'],
                    clientUuid: $clientUuid,
                    items: $items,
                );
            }
        } catch (FrozenWalletException|InsufficientBalanceException|DailyLimitExceededException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'transaction' => [
                'id' => $tx->id,
                'summary' => CanteenPosService::summarizeItems($tx->items),
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
            'receipt' => [
                'number' => 'KTN-'.str_pad((string) $tx->id, 6, '0', STR_PAD_LEFT),
                'items' => array_map(static fn (array $i): array => [
                    'name' => $i['name'], 'qty' => $i['qty'] ?? 1, 'price' => $i['price'] ?? $i['amount'], 'amount' => $i['amount'],
                ], $tx->items ?? [['name' => 'Belanja kantin', 'amount' => $tx->total]]),
                'balance_after' => (int) Wallet::query()->where('student_id', $student->id)->value('balance'),
                'cashier' => $user->name,
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
