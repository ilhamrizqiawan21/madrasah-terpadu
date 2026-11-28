<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\StudentStatus;
use App\Enums\WalletEntryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WalletTopupRequest;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletEntry;
use App\Notifications\WalletTopupNotification;
use App\Services\Notifications\GuardianNotifier;
use App\Services\Settings\SettingsRepository;
use App\Services\Wallet\WalletService;
use App\Support\SchoolContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengelolaan saldo dompet kantin dan isi saldo tunai di loket (T-804).
 */
class WalletController extends Controller
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly GuardianNotifier $notifier,
        private readonly SettingsRepository $settings,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $context = SchoolContext::resolve($request->session());
        $unit = $context['unit'] !== null
            ? Unit::query()->where('code', $context['unit'])->first()
            : null;

        $search = $request->string('search')->trim()->toString();

        $wallets = Wallet::query()
            ->with('student.unit:id,code,name')
            ->whereHas('student', function (Builder $q) use ($unit, $search): void {
                $q->where('status', StudentStatus::Active->value);
                if ($unit !== null) {
                    $q->where('unit_id', $unit->id);
                }
                if ($search !== '') {
                    $term = '%'.$search.'%';
                    $q->where(fn (Builder $sq) => $sq->where('name', 'like', $term)->orWhere('nis', 'like', $term));
                }
            })
            ->orderByDesc('updated_at')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Wallet $w): array => [
                'id' => $w->id,
                'student_id' => $w->student_id,
                'nis' => $w->student->nis,
                'name' => $w->student->name,
                'unit_code' => $w->student->unit->code,
                'balance' => $w->balance,
                'daily_limit' => $w->daily_limit,
                'is_frozen' => $w->is_frozen,
            ]);

        $recentTopups = WalletEntry::query()
            ->with(['wallet.student:id,nis,name', 'creator:id,name'])
            ->where('type', WalletEntryType::Topup->value)
            ->when($unit !== null, fn (Builder $q) => $q->whereHas('wallet.student', fn (Builder $s) => $s->where('unit_id', $unit->id)))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(15)
            ->get()
            ->map(fn (WalletEntry $entry): array => [
                'id' => $entry->id,
                'student_name' => $entry->wallet->student->name,
                'student_nis' => $entry->wallet->student->nis,
                'amount' => $entry->amount,
                'balance_after' => $entry->balance_after,
                'actor_name' => $entry->creator !== null ? $entry->creator->name : 'Sistem',
                'created_at' => $entry->created_at->toIso8601String(),
            ])
            ->values()
            ->all();

        $students = Student::query()
            ->where('status', StudentStatus::Active->value)
            ->when($unit !== null, fn (Builder $q) => $q->where('unit_id', $unit->id))
            ->orderBy('name')
            ->get(['id', 'nis', 'name'])
            ->map(fn (Student $s): array => ['id' => $s->id, 'nis' => $s->nis, 'name' => $s->name])
            ->values()
            ->all();

        return Inertia::render('admin/wallets/index', [
            'wallets' => $wallets,
            'recentTopups' => $recentTopups,
            'students' => $students,
            'filters' => ['search' => $search],
            'limits' => [
                'min_top_up' => (int) config('sekolah.wallet.min_top_up', 10_000),
                'max_top_up' => (int) config('sekolah.wallet.max_top_up', 1_000_000),
            ],
            'canTopup' => $user->can(Permission::WalletTopup->value),
        ]);
    }

    public function topUp(WalletTopupRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        /** @var array{student_id: int, amount: int, client_uuid: string, note?: ?string} $validated */
        $validated = $request->validated();
        $student = Student::query()->findOrFail((int) $validated['student_id']);
        $idempotencyKey = "topup-cash:{$validated['client_uuid']}";

        $alreadyExists = WalletEntry::query()->where('idempotency_key', $idempotencyKey)->exists();

        $entry = $this->walletService->topUp(
            student: $student,
            amount: (int) $validated['amount'],
            idempotencyKey: $idempotencyKey,
            actor: $user,
            description: isset($validated['note']) && trim($validated['note']) !== ''
                ? 'Isi saldo tunai: '.trim($validated['note'])
                : 'Isi saldo tunai di loket',
        );

        if (! $alreadyExists) {
            $this->notifier->notifyStudentGuardians(
                $student,
                new WalletTopupNotification(
                    entryId: $entry->id,
                    studentName: $student->nickname ?? $student->name,
                    amount: $entry->amount,
                    balanceAfter: $entry->balance_after,
                ),
            );
        }

        return back()->with('success', 'Isi saldo tunai berhasil dicatat.');
    }

    public function receipt(WalletEntry $entry): HttpResponse
    {
        abort_if($entry->type !== WalletEntryType::Topup, 404);

        $entry->load(['wallet.student', 'creator:id,name']);
        $identity = $this->settings->identity();
        $schoolName = $identity['app_name'] ?: $identity['foundation_name'];

        $pdf = Pdf::loadView('pdf.wallet-topup-receipt', [
            'schoolName' => $schoolName,
            'entry' => $entry,
        ])->setPaper('a5', 'portrait');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"kuitansi-topup-{$entry->id}.pdf\"",
        ]);
    }
}
