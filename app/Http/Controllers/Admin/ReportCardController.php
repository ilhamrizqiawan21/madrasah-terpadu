<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\ReportCard;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Elearning\ReportCardService;
use App\Support\AppTimezone;
use App\Support\SchoolContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * E-Learning > E-Rapor (unggah PDF per siswa per semester, T-1604).
 */
class ReportCardController extends Controller
{
    public function __construct(
        private readonly ReportCardService $service,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ReportCard::class);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $context = SchoolContext::resolve($request->session());
        $academicYearId = $request->integer('academic_year_id')
            ?: ($context['academicYearId'] ?? (int) AcademicYear::query()->where('is_active', true)->value('id'));

        $semester = $request->string('semester')->toString();
        if (! in_array($semester, ['odd', 'even'], true)) {
            $semester = 'odd';
        }

        $classroomId = $request->integer('classroom_id') ?: null;
        $search = trim($request->string('search')->toString());

        $isAdmin = $user->hasRole(Role::Admin->value);
        $isTeacher = $user->hasRole(Role::Teacher->value);
        $unitId = $context['unit'] !== null
            ? Unit::query()->where('code', $context['unit'])->value('id')
            : null;

        $classrooms = Classroom::query()
            ->when($academicYearId > 0, fn (Builder $q) => $q->where('academic_year_id', $academicYearId))
            ->when($unitId !== null, fn (Builder $q) => $q->where('unit_id', $unitId))
            ->when($isTeacher && ! $isAdmin, fn (Builder $q) => $q->where('homeroom_teacher_id', $user->id))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'unit_id', 'name', 'homeroom_teacher_id']);

        $selectedClassroomId = $classroomId ?? $classrooms->first()?->id;

        $paginator = Enrollment::query()
            ->with(['student:id,nis,name', 'classroom:id,name,homeroom_teacher_id'])
            ->where('academic_year_id', $academicYearId)
            ->when($selectedClassroomId !== null, fn (Builder $q) => $q->where('classroom_id', $selectedClassroomId))
            ->when($isTeacher && ! $isAdmin, fn (Builder $q) => $q->whereHas('classroom', fn (Builder $cq) => $cq->where('homeroom_teacher_id', $user->id)))
            ->when($search !== '', fn (Builder $q) => $q->whereHas('student', fn (Builder $sq) => $sq
                ->where('name', 'like', "%{$search}%")
                ->orWhere('nis', 'like', "%{$search}%")))
            ->join('students', 'students.id', '=', 'enrollments.student_id')
            ->orderBy('students.name')
            ->select('enrollments.*')
            ->paginate(25)
            ->withQueryString();

        $studentIds = $paginator->getCollection()->pluck('student_id')->all();
        $cardsByStudent = ReportCard::query()
            ->with('uploader:id,name')
            ->where('academic_year_id', $academicYearId)
            ->where('semester', $semester)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        $tz = AppTimezone::name();
        $paginator->through(function (Enrollment $enrollment) use ($cardsByStudent, $tz, $isAdmin, $user): array {
            /** @var ReportCard|null $card */
            $card = $cardsByStudent->get($enrollment->student_id);
            $canEdit = $isAdmin || $enrollment->classroom->homeroom_teacher_id === $user->id;

            return [
                'student_id' => $enrollment->student_id,
                'nis' => $enrollment->student->nis,
                'student_name' => $enrollment->student->name,
                'classroom_name' => $enrollment->classroom->name,
                'can_manage' => $canEdit,
                'report_card' => $card !== null ? [
                    'id' => $card->id,
                    'published_at' => $card->published_at?->setTimezone($tz)->toIso8601String(),
                    'uploader_name' => $card->uploader->name,
                    'updated_at' => $card->updated_at->setTimezone($tz)->toIso8601String(),
                ] : null,
            ];
        });

        return Inertia::render('admin/elearning/report-cards', [
            'rows' => $paginator,
            'classrooms' => $classrooms->map(fn (Classroom $c): array => [
                'id' => $c->id,
                'name' => $c->name,
            ])->values(),
            'academicYears' => AcademicYear::query()->orderByDesc('starts_on')->get(['id', 'name', 'is_active']),
            'filters' => [
                'academic_year_id' => $academicYearId,
                'semester' => $semester,
                'classroom_id' => $selectedClassroomId,
                'search' => $search,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'semester' => ['required', 'in:odd,even'],
            'file' => ['required', 'file', 'mimetypes:application/pdf', 'max:10240'],
            'publish_now' => ['sometimes', 'boolean'],
        ], [], [
            'student_id' => 'siswa',
            'academic_year_id' => 'tahun ajaran',
            'semester' => 'semester',
            'file' => 'berkas PDF rapor',
        ]);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $student = Student::query()->findOrFail((int) $validated['student_id']);
        $academicYear = AcademicYear::query()->findOrFail((int) $validated['academic_year_id']);

        Gate::authorize('manage', [ReportCard::class, $student, $academicYear->id]);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        $this->service->upload(
            $user,
            $student,
            $academicYear,
            (string) $validated['semester'],
            $file,
            (bool) ($validated['publish_now'] ?? false),
        );

        return back()->with('success', 'PDF e-rapor berhasil diunggah.');
    }

    public function togglePublish(Request $request, ReportCard $reportCard): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $reportCard->loadMissing('student');
        Gate::authorize('manage', [ReportCard::class, $reportCard->student, $reportCard->academic_year_id]);

        $publish = $reportCard->published_at === null;
        $this->service->setPublished($user, $reportCard, $publish);

        return back()->with('success', $publish ? 'E-rapor diterbitkan ke wali.' : 'Penerbitan e-rapor dibatalkan.');
    }

    public function destroy(Request $request, ReportCard $reportCard): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $reportCard->loadMissing('student');
        Gate::authorize('manage', [ReportCard::class, $reportCard->student, $reportCard->academic_year_id]);

        $this->service->delete($user, $reportCard);

        return back()->with('success', 'Berkas e-rapor dihapus.');
    }

    public function download(ReportCard $reportCard): StreamedResponse
    {
        Gate::authorize('viewAny', ReportCard::class);

        abort_unless(Storage::disk('local')->exists($reportCard->file_path), 404);

        return Storage::disk('local')->download(
            $reportCard->file_path,
            "rapor-siswa-{$reportCard->student_id}-{$reportCard->semester}.pdf",
        );
    }
}
