<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CharacterReviewStatus;
use App\Enums\CharacterSource;
use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Character\CorrectAssessmentRequest;
use App\Models\CharacterAssessment;
use App\Models\CharacterComponent;
use App\Models\CharacterDimension;
use App\Models\CharacterProfile;
use App\Models\CharacterReview;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Unit;
use App\Models\User;
use App\Services\Character\CharacterAssessmentService;
use App\Services\Character\CharacterReportService;
use App\Support\SchoolContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Kesiswaan > Penilaian karakter (ADR-029, T-1203). Wali kelas menghitung & mengoreksi rombelnya,
 * kepala unit mengubah capaian, menyetujui, atau membuka kunci.
 */
class CharacterAssessmentController extends Controller
{
    public function __construct(private readonly CharacterAssessmentService $service) {}

    public function index(Request $request): Response
    {
        $user = $this->actor($request);
        $context = SchoolContext::resolve($request->session());
        $unit = $context['unit'] !== null ? Unit::query()->where('code', $context['unit'])->first() : null;
        $yearId = $context['academicYearId'];

        $classrooms = Classroom::query()
            ->where('is_active', true)
            ->when($yearId !== null, fn (Builder $q) => $q->where('academic_year_id', $yearId), fn (Builder $q) => $q->whereRaw('1 = 0'))
            ->when($unit !== null, fn (Builder $q) => $q->where('unit_id', $unit->id))
            ->when(! $this->seesAll($user), fn (Builder $q) => $q->where('homeroom_teacher_id', $user->id))
            ->with('homeroomTeacher:id,name')
            ->orderBy('grade')
            ->orderBy('name')
            ->get();

        $studentCounts = Enrollment::query()
            ->whereIn('classroom_id', $classrooms->modelKeys())
            ->selectRaw('classroom_id, count(*) as aggregate')
            ->groupBy('classroom_id')
            ->pluck('aggregate', 'classroom_id');

        $reviews = CharacterReview::query()
            ->whereIn('classroom_id', $classrooms->modelKeys())
            ->get()
            ->keyBy('classroom_id');

        return Inertia::render('admin/character/index', [
            'academicYear' => $context['academicYear'],
            'configured' => $unit === null || CharacterProfile::query()->where('unit_id', $unit->id)->exists(),
            'classrooms' => $classrooms->map(function (Classroom $c) use ($reviews, $studentCounts): array {
                /** @var CharacterReview|null $review */
                $review = $reviews->get($c->id);

                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'homeroom' => $c->homeroomTeacher?->name,
                    'students_count' => (int) ($studentCounts[$c->id] ?? 0),
                    'status' => $review->status->value ?? null,
                    'status_label' => $review?->status->label() ?? 'Belum dihitung',
                    'computed_at' => $review?->computed_at?->toIso8601String(),
                ];
            })->values()->all(),
        ]);
    }

    public function show(Request $request, Classroom $classroom): Response
    {
        $user = $this->actor($request);
        $this->authorizeView($user, $classroom);

        $profile = CharacterProfile::query()->where('unit_id', $classroom->unit_id)->first();
        $review = CharacterReview::query()
            ->with(['submitter:id,name', 'approver:id,name'])
            ->where('classroom_id', $classroom->id)
            ->where('academic_year_id', $classroom->academic_year_id)
            ->first();

        $dimensions = CharacterDimension::query()
            ->with('components')
            ->where('unit_id', $classroom->unit_id)
            ->where('is_active', true)
            ->orderBy('position')
            ->get();

        $assessments = $review !== null
            ? $review->assessments()->get()->groupBy('student_id')
            : collect();

        $students = Enrollment::query()
            ->with('student:id,name,nis')
            ->where('classroom_id', $classroom->id)
            ->where('academic_year_id', $classroom->academic_year_id)
            ->get()
            ->sortBy(fn (Enrollment $e): string => $e->student->name)
            ->values();

        $status = $review->status ?? CharacterReviewStatus::Draft;

        return Inertia::render('admin/character/show', [
            'classroom' => ['id' => $classroom->id, 'name' => $classroom->name, 'semester' => $classroom->academicYear->name.' '.$classroom->academicYear->semester->label()],
            'scale' => $profile->scale ?? [],
            'configured' => $profile !== null,
            'dimensions' => $dimensions->map(fn (CharacterDimension $d): array => [
                'id' => $d->id,
                'name' => $d->name,
                'has_observation' => $d->components->contains(fn (CharacterComponent $c): bool => $c->source === CharacterSource::TeacherObservation),
            ])->values()->all(),
            'review' => $review !== null ? [
                'status' => $review->status->value,
                'status_label' => $review->status->label(),
                'computed_at' => $review->computed_at?->toIso8601String(),
                'submitted_by' => $review->submitter?->name,
                'submitted_at' => $review->submitted_at?->toIso8601String(),
                'approved_by' => $review->approver?->name,
                'approved_at' => $review->approved_at?->toIso8601String(),
                'reopen_reason' => $review->reopen_reason,
            ] : null,
            'rows' => $students->map(function (Enrollment $e) use ($assessments): array {
                /** @var Collection<int, CharacterAssessment> $own */
                $own = $assessments->get($e->student_id, collect());

                return [
                    'student_id' => $e->student_id,
                    'name' => $e->student->name,
                    'nis' => $e->student->nis,
                    'assessments' => $own->mapWithKeys(fn (CharacterAssessment $a): array => [$a->character_dimension_id => [
                        'id' => $a->id,
                        'computed_score' => $a->computed_score !== null ? (float) $a->computed_score : null,
                        'computed_level' => $a->computed_level,
                        'final_level' => $a->final_level,
                        'override_reason' => $a->override_reason,
                        'observation_level' => $a->observation_level,
                        'description' => $a->description,
                        'description_edited' => $a->description_edited,
                        'components' => array_map(fn (array $c): array => [
                            'phrase' => $c['phrase'],
                            'weight' => $c['weight'],
                            'effective_weight' => $c['effective_weight'],
                            'score' => $c['score'],
                            'coverage' => $c['coverage'],
                            'used' => $c['used'],
                            'reason' => $c['reason'],
                        ], (array) ($a->inputs['components'] ?? [])),
                    ]])->all(),
                ];
            })->all(),
            'can' => [
                'manage' => $status === CharacterReviewStatus::Draft && $this->managesDraft($user, $classroom),
                'review' => $status === CharacterReviewStatus::Submitted && $this->reviews($user),
                'reopen' => $status !== CharacterReviewStatus::Draft && $this->reviews($user),
            ],
        ]);
    }

    public function compute(Request $request, Classroom $classroom): RedirectResponse
    {
        $user = $this->actor($request);
        abort_unless($this->managesDraft($user, $classroom), 403);

        $this->service->compute($classroom, $classroom->academicYear, $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Penilaian dihitung dari data terbaru. Koreksi Anda tetap dipertahankan.']);

        return back();
    }

    public function correct(CorrectAssessmentRequest $request, CharacterAssessment $assessment): RedirectResponse
    {
        $user = $this->actor($request);
        $review = $assessment->review;
        $classroom = $review->classroom;

        $allowed = match ($review->status) {
            CharacterReviewStatus::Draft => $this->managesDraft($user, $classroom),
            CharacterReviewStatus::Submitted => $this->reviews($user),
            CharacterReviewStatus::Approved => false,
        };
        abort_unless($allowed, 403);

        $this->service->correct($assessment, $request->correction(), $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Penilaian siswa disimpan.']);

        return back();
    }

    public function submit(Request $request, Classroom $classroom): RedirectResponse
    {
        $user = $this->actor($request);
        abort_unless($this->managesDraft($user, $classroom), 403);

        $this->service->submit($this->review($classroom), $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Penilaian diajukan ke kepala unit dan dikunci.']);

        return back();
    }

    public function approve(Request $request, Classroom $classroom): RedirectResponse
    {
        $user = $this->actor($request);
        abort_unless($this->reviews($user), 403);

        $this->service->approve($this->review($classroom), $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Penilaian disetujui.']);

        return back();
    }

    public function reopen(Request $request, Classroom $classroom): RedirectResponse
    {
        $user = $this->actor($request);
        abort_unless($this->reviews($user), 403);
        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];

        $this->service->reopen($this->review($classroom), (string) $reason, $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kunci dibuka; penilaian kembali menjadi draf.']);

        return back();
    }

    public function pdf(Request $request, Classroom $classroom, CharacterReportService $reports): HttpResponse
    {
        $user = $this->actor($request);
        $this->authorizeView($user, $classroom);
        $review = $this->review($classroom);
        $studentId = $request->integer('student') ?: null;

        $name = 'karakter-'.str($classroom->name)->slug().($studentId !== null ? '-'.$studentId : '').'.pdf';

        return response($reports->pdf($review, $studentId), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$name.'"',
        ]);
    }

    public function excel(Request $request, Classroom $classroom, CharacterReportService $reports): BinaryFileResponse
    {
        $user = $this->actor($request);
        $this->authorizeView($user, $classroom);

        return response()
            ->download($reports->excel($this->review($classroom)), 'karakter-'.str($classroom->name)->slug().'.xlsx')
            ->deleteFileAfterSend(true);
    }

    private function review(Classroom $classroom): CharacterReview
    {
        return CharacterReview::query()
            ->where('classroom_id', $classroom->id)
            ->where('academic_year_id', $classroom->academic_year_id)
            ->firstOrFail();
    }

    private function authorizeView(User $user, Classroom $classroom): void
    {
        abort_unless($this->seesAll($user) || $classroom->homeroom_teacher_id === $user->id, 403);
    }

    private function seesAll(User $user): bool
    {
        return $user->hasAnyRole([Role::Admin->value, Role::UnitHead->value, Role::Counselor->value]);
    }

    private function managesDraft(User $user, Classroom $classroom): bool
    {
        return $user->hasRole(Role::Admin->value)
            || ($user->can(Permission::AttitudeLock->value) && $classroom->homeroom_teacher_id === $user->id);
    }

    private function reviews(User $user): bool
    {
        return $user->hasRole(Role::Admin->value) || $user->can(Permission::AttitudeApprove->value);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
