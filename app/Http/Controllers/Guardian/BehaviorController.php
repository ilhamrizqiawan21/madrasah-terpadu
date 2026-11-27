<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guardian;

use App\Enums\BehaviorRecordStatus;
use App\Enums\PointAccount;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\BehaviorRecord;
use App\Models\CanteenVoucher;
use App\Models\DisciplineFollowup;
use App\Models\RewardOffer;
use App\Models\RewardRedemption;
use App\Models\Student;
use App\Models\User;
use App\Services\Behavior\PointLedgerService;
use App\Services\Behavior\RewardRedemptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BehaviorController extends Controller
{
    public function __construct(
        private readonly PointLedgerService $ledger,
        private readonly RewardRedemptionService $redemptions,
    ) {}

    public function index(Student $student): Response
    {
        $activeYear = AcademicYear::query()->where('is_active', true)->first();
        $timezone = config('sekolah.timezone', 'Asia/Makassar');
        $today = now()->timezone(is_string($timezone) ? $timezone : 'Asia/Makassar')->toDateString();

        $violationPoints = $activeYear !== null
            ? $this->ledger->balanceFor($student->id, $activeYear->id, PointAccount::Violation)
            : 0;
        $meritPoints = $activeYear !== null
            ? $this->ledger->balanceFor($student->id, $activeYear->id, PointAccount::Merit)
            : 0;

        $records = BehaviorRecord::query()
            ->with('pointRule:id,code,name')
            ->where('student_id', $student->id)
            ->when($activeYear !== null, fn ($q) => $q->where('academic_year_id', $activeYear->id))
            ->where('status', BehaviorRecordStatus::Active->value)
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(fn (BehaviorRecord $rec): array => [
                'id' => $rec->id,
                'rule_name' => $rec->pointRule->name,
                'category' => $rec->category->value,
                'category_label' => $rec->category->label(),
                'points' => $rec->points,
                'occurred_on' => $rec->occurred_on->toDateString(),
                'note' => $rec->note,
            ])
            ->values()
            ->all();

        $followups = DisciplineFollowup::query()
            ->with('threshold:id,points,action_name,notify_guardian')
            ->where('student_id', $student->id)
            ->when($activeYear !== null, fn ($q) => $q->where('academic_year_id', $activeYear->id))
            ->whereHas('threshold', fn ($q) => $q->where('notify_guardian', true))
            ->orderByDesc('triggered_at')
            ->get()
            ->map(fn (DisciplineFollowup $f): array => [
                'id' => $f->id,
                'action_name' => $f->threshold->action_name,
                'threshold_points' => $f->threshold->points,
                'status' => $f->status,
                'triggered_at' => $f->triggered_at->toDateString(),
            ])
            ->values()
            ->all();

        $offers = RewardOffer::query()
            ->active()
            ->where(function ($q) use ($student): void {
                $q->where('unit_id', $student->unit_id)->orWhereNull('unit_id');
            })
            ->where(function ($q): void {
                $q->whereNull('stock')->orWhere('stock', '>', 0);
            })
            ->orderBy('cost_points')
            ->get()
            ->map(fn (RewardOffer $o): array => [
                'id' => $o->id,
                'name' => $o->name,
                'cost_points' => $o->cost_points,
                'voucher_amount' => $o->voucher_amount,
                'voucher_valid_days' => $o->voucher_valid_days,
                'eligible' => $meritPoints >= $o->cost_points,
            ])
            ->values()
            ->all();

        $vouchers = CanteenVoucher::query()
            ->with('redemption.offer:id,name')
            ->where('student_id', $student->id)
            ->where('remaining', '>', 0)
            ->whereDate('expires_on', '>=', $today)
            ->orderBy('expires_on')
            ->get()
            ->map(fn (CanteenVoucher $v): array => [
                'id' => $v->id,
                'offer_name' => $v->redemption->offer->name,
                'amount' => $v->amount,
                'remaining' => $v->remaining,
                'expires_on' => $v->expires_on->toDateString(),
            ])
            ->values()
            ->all();

        $recentRedemptions = RewardRedemption::query()
            ->with('offer:id,name')
            ->where('student_id', $student->id)
            ->when($activeYear !== null, fn ($q) => $q->where('academic_year_id', $activeYear->id))
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (RewardRedemption $r): array => [
                'id' => $r->id,
                'offer_name' => $r->offer->name,
                'cost_points' => $r->cost_points,
                'status' => $r->status,
                'decision_note' => $r->decision_note,
            ])
            ->values()
            ->all();

        return Inertia::render('guardian/behavior', [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'nis' => $student->nis,
            ],
            'summary' => [
                'violation_points' => $violationPoints,
                'merit_points' => $meritPoints,
                'academic_year' => $activeYear?->label(),
            ],
            'records' => $records,
            'followups' => $followups,
            'offers' => $offers,
            'vouchers' => $vouchers,
            'redemptions' => $recentRedemptions,
        ]);
    }

    public function requestRedemption(Request $request, Student $student): RedirectResponse
    {
        $validated = $request->validate([
            'reward_offer_id' => ['required', 'integer', 'exists:reward_offers,id'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $offer = RewardOffer::query()->findOrFail((int) $validated['reward_offer_id']);

        $this->redemptions->request($actor, $student, $offer);

        return back()->with('success', 'Pengajuan penukaran reward berhasil dikirim ke BK.');
    }
}
