<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\RewardOffer;
use App\Models\RewardRedemption;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Behavior\RewardOfferService;
use App\Services\Behavior\RewardRedemptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RewardController extends Controller
{
    public function __construct(
        private readonly RewardOfferService $offerService,
        private readonly RewardRedemptionService $redemptionService,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', RewardRedemption::class);

        $status = $request->string('status')->toString() ?: 'pending';
        $search = trim($request->string('search')->toString());
        $activeYear = AcademicYear::query()->where('is_active', true)->first();

        $redemptions = RewardRedemption::query()
            ->with([
                'student:id,nis,name',
                'offer:id,name',
                'requester:id,name',
                'decider:id,name',
                'voucher:id,reward_redemption_id,expires_on,remaining',
            ])
            ->when($activeYear !== null, fn ($q) => $q->where('academic_year_id', $activeYear->id))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->whereHas('student', fn ($s) => $s->where(fn ($w) => $w->whereLike('name', "%{$search}%")->orWhereLike('nis', "%{$search}%"))))
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $redemptionItems = $redemptions->getCollection()
            ->map(fn (RewardRedemption $r): array => [
                'id' => $r->id,
                'student_name' => $r->student->name,
                'student_nis' => $r->student->nis,
                'offer_name' => $r->offer->name,
                'cost_points' => $r->cost_points,
                'voucher_amount' => $r->voucher_amount,
                'requester_name' => $r->requester->name,
                'status' => $r->status,
                'decision_note' => $r->decision_note,
                'decider_name' => $r->decider->name ?? null,
                'voucher_expires_on' => $r->voucher?->expires_on->toDateString(),
                'created_at' => $r->created_at?->toDateTimeString() ?? '',
            ])
            ->values()
            ->all();

        $offers = RewardOffer::query()
            ->with('unit:id,code,name')
            ->orderBy('is_active', 'desc')
            ->orderBy('cost_points')
            ->get()
            ->map(fn (RewardOffer $o): array => [
                'id' => $o->id,
                'unit_id' => $o->unit_id,
                'unit_name' => $o->unit->name ?? 'Semua unit',
                'name' => $o->name,
                'cost_points' => $o->cost_points,
                'voucher_amount' => $o->voucher_amount,
                'voucher_valid_days' => $o->voucher_valid_days,
                'stock' => $o->stock,
                'is_active' => $o->is_active,
            ])
            ->all();

        $students = Student::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'nis', 'name'])
            ->map(fn (Student $s): array => [
                'id' => $s->id,
                'label' => "{$s->name} ({$s->nis})",
            ])
            ->all();

        $user = $request->user();

        return Inertia::render('admin/behavior/rewards', [
            'redemptions' => [
                'data' => $redemptionItems,
                'meta' => [
                    'current_page' => $redemptions->currentPage(),
                    'last_page' => $redemptions->lastPage(),
                    'per_page' => $redemptions->perPage(),
                    'total' => $redemptions->total(),
                    'from' => $redemptions->firstItem(),
                    'to' => $redemptions->lastItem(),
                ],
            ],
            'offers' => $offers,
            'students' => $students,
            'units' => Unit::query()->orderBy('name')->get(['id', 'code', 'name']),
            'filters' => ['status' => $status, 'search' => $search],
            'canApprove' => $user instanceof User && $user->can('decide', new RewardRedemption),
            'canRequest' => $user instanceof User && $user->can('request', RewardRedemption::class),
        ]);
    }

    public function storeOffer(Request $request): RedirectResponse
    {
        Gate::authorize('create', RewardOffer::class);

        $validated = $request->validate([
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'name' => ['required', 'string', 'max:150'],
            'cost_points' => ['required', 'integer', 'min:1', 'max:5000'],
            'voucher_amount' => ['required', 'integer', 'min:1000', 'max:1000000'],
            'voucher_valid_days' => ['required', 'integer', 'min:1', 'max:365'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $this->offerService->create($actor, $validated);

        return back()->with('success', 'Penawaran reward berhasil ditambahkan.');
    }

    public function updateOffer(Request $request, RewardOffer $rewardOffer): RedirectResponse
    {
        Gate::authorize('update', $rewardOffer);

        $validated = $request->validate([
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'name' => ['required', 'string', 'max:150'],
            'cost_points' => ['required', 'integer', 'min:1', 'max:5000'],
            'voucher_amount' => ['required', 'integer', 'min:1000', 'max:1000000'],
            'voucher_valid_days' => ['required', 'integer', 'min:1', 'max:365'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $this->offerService->update($actor, $rewardOffer, $validated);

        return back()->with('success', 'Penawaran reward berhasil diperbarui.');
    }

    public function toggleOffer(Request $request, RewardOffer $rewardOffer): RedirectResponse
    {
        Gate::authorize('toggle', $rewardOffer);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $this->offerService->toggleActive($actor, $rewardOffer, (bool) $validated['is_active']);

        return back()->with('success', $validated['is_active'] ? 'Penawaran diaktifkan.' : 'Penawaran dinonaktifkan.');
    }

    public function requestRedemption(Request $request): RedirectResponse
    {
        Gate::authorize('request', RewardRedemption::class);

        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'reward_offer_id' => ['required', 'integer', 'exists:reward_offers,id'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $student = Student::query()->findOrFail((int) $validated['student_id']);
        $offer = RewardOffer::query()->findOrFail((int) $validated['reward_offer_id']);

        $this->redemptionService->request($actor, $student, $offer);

        return back()->with('success', 'Pengajuan penukaran reward berhasil dibuat.');
    }

    public function approve(Request $request, RewardRedemption $rewardRedemption): RedirectResponse
    {
        Gate::authorize('decide', $rewardRedemption);

        $validated = $request->validate([
            'decision_note' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $this->redemptionService->approve(
            $actor,
            $rewardRedemption,
            isset($validated['decision_note']) ? (string) $validated['decision_note'] : null,
        );

        return back()->with('success', 'Penukaran reward disetujui dan voucher kantin diterbitkan.');
    }

    public function reject(Request $request, RewardRedemption $rewardRedemption): RedirectResponse
    {
        Gate::authorize('decide', $rewardRedemption);

        $validated = $request->validate([
            'decision_note' => ['required', 'string', 'max:500'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $this->redemptionService->reject($actor, $rewardRedemption, (string) $validated['decision_note']);

        return back()->with('success', 'Pengajuan penukaran reward ditolak.');
    }
}
