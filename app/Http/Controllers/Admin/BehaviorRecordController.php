<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\BehaviorRecordStatus;
use App\Enums\PointCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Behavior\StoreBehaviorRecordsRequest;
use App\Http\Requests\Admin\Behavior\VoidBehaviorRecordRequest;
use App\Models\AcademicYear;
use App\Models\BehaviorRecord;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\PointRule;
use App\Models\User;
use App\Services\Behavior\BehaviorRecordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BehaviorRecordController extends Controller
{
    public function __construct(
        private readonly BehaviorRecordService $service,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', BehaviorRecord::class);

        $search = trim((string) $request->string('search'));
        $category = $request->string('category')->toString();
        $status = $request->string('status')->toString();
        $date = $request->string('date')->toString();

        $activeYear = AcademicYear::query()->where('is_active', true)->first();

        $records = BehaviorRecord::query()
            ->with([
                'student:id,nis,name,unit_id',
                'pointRule:id,code,name,level',
                'recorder:id,name',
                'voider:id,name',
            ])
            ->when($activeYear !== null, fn ($q) => $q->where('academic_year_id', $activeYear->id))
            ->when($search !== '', function ($q) use ($search): void {
                $q->whereHas('student', function ($sq) use ($search): void {
                    $sq->where('name', 'like', "%{$search}%")
                        ->orWhere('nis', 'like', "%{$search}%");
                });
            })
            ->when($category !== '', fn ($q) => $q->where('category', $category))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($date !== '', fn ($q) => $q->whereDate('occurred_on', $date))
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $items = $records->getCollection()
            ->map(fn (BehaviorRecord $rec): array => [
                'id' => $rec->id,
                'student_id' => $rec->student_id,
                'student_name' => $rec->student->name,
                'student_nis' => $rec->student->nis,
                'rule_code' => $rec->pointRule->code,
                'rule_name' => $rec->pointRule->name,
                'category' => $rec->category->value,
                'category_label' => $rec->category->label(),
                'points' => $rec->points,
                'occurred_on' => $rec->occurred_on->toDateString(),
                'note' => $rec->note,
                'has_evidence' => $rec->evidence_path !== null,
                'recorded_by_name' => $rec->recorder->name,
                'status' => $rec->status->value,
                'status_label' => $rec->status->label(),
                'void_reason' => $rec->void_reason,
                'voided_by_name' => $rec->voider->name ?? null,
            ])
            ->values()
            ->all();

        $classrooms = $activeYear !== null
            ? Classroom::query()
                ->with('unit:id,code')
                ->where('academic_year_id', $activeYear->id)
                ->where('is_active', true)
                ->orderBy('grade')
                ->orderBy('name')
                ->get(['id', 'unit_id', 'grade', 'name'])
                ->map(fn (Classroom $c): array => [
                    'id' => $c->id,
                    'label' => ($c->unit->code ?? '').' · '.$c->name,
                ])
                ->all()
            : [];

        $enrollments = $activeYear !== null
            ? Enrollment::query()
                ->with('student:id,nis,name,unit_id')
                ->where('academic_year_id', $activeYear->id)
                ->get(['student_id', 'classroom_id'])
                ->map(fn (Enrollment $e): array => [
                    'student_id' => $e->student_id,
                    'classroom_id' => $e->classroom_id,
                    'nis' => $e->student->nis,
                    'name' => $e->student->name,
                ])
                ->sortBy('name')
                ->values()
                ->all()
            : [];

        $rules = PointRule::query()
            ->active()
            ->orderBy('category')
            ->orderBy('code')
            ->get(['id', 'unit_id', 'code', 'name', 'category', 'level', 'points'])
            ->map(fn (PointRule $r): array => [
                'id' => $r->id,
                'code' => $r->code,
                'name' => $r->name,
                'category' => $r->category->value,
                'category_label' => $r->category->label(),
                'level' => $r->level,
                'points' => $r->points,
            ])
            ->all();

        $user = $request->user();

        return Inertia::render('admin/behavior/index', [
            'records' => [
                'data' => $items,
                'meta' => [
                    'current_page' => $records->currentPage(),
                    'last_page' => $records->lastPage(),
                    'per_page' => $records->perPage(),
                    'total' => $records->total(),
                    'from' => $records->firstItem(),
                    'to' => $records->lastItem(),
                ],
            ],
            'classrooms' => $classrooms,
            'students' => $enrollments,
            'rules' => $rules,
            'categories' => array_map(
                static fn (PointCategory $cat): array => ['value' => $cat->value, 'label' => $cat->label()],
                PointCategory::cases()
            ),
            'filters' => [
                'search' => $search,
                'category' => $category,
                'status' => $status,
                'date' => $date,
            ],
            'today' => now()->timezone(config('sekolah.timezone', 'Asia/Makassar'))->toDateString(),
            'canRecord' => $user instanceof User && $user->can('create', BehaviorRecord::class),
            'canVoid' => $user instanceof User && $user->can('void', new BehaviorRecord),
            'statuses' => array_map(
                static fn (BehaviorRecordStatus $s): array => ['value' => $s->value, 'label' => $s->label()],
                BehaviorRecordStatus::cases()
            ),
        ]);
    }

    public function store(StoreBehaviorRecordsRequest $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();
        $rule = PointRule::query()->findOrFail((int) $validated['point_rule_id']);

        /** @var list<int> $studentIds */
        $studentIds = array_map('intval', $validated['student_ids']);

        $records = $this->service->recordMany(
            actor: $actor,
            studentIds: $studentIds,
            rule: $rule,
            occurredOn: (string) $validated['occurred_on'],
            note: isset($validated['note']) ? (string) $validated['note'] : null,
            evidence: $request->file('evidence'),
        );

        return back()->with('success', $records->count().' catatan perilaku berhasil disimpan.');
    }

    public function void(VoidBehaviorRecordRequest $request, BehaviorRecord $behaviorRecord): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->service->void($actor, $behaviorRecord, (string) $request->validated('void_reason'));

        return back()->with('success', 'Catatan perilaku dibatalkan dan saldo poin dipulihkan.');
    }

    public function evidence(BehaviorRecord $behaviorRecord): StreamedResponse
    {
        Gate::authorize('viewEvidence', $behaviorRecord);

        if ($behaviorRecord->evidence_path === null || ! Storage::disk('local')->exists($behaviorRecord->evidence_path)) {
            abort(404);
        }

        return Storage::disk('local')->response($behaviorRecord->evidence_path);
    }
}
