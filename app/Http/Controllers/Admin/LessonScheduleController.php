<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\LessonPeriod;
use App\Models\LessonSchedule;
use App\Models\Room;
use App\Models\Subject;
use App\Models\Unit;
use App\Models\User;
use App\Services\Academic\LessonScheduleService;
use App\Support\SchoolContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Akademik > Jam Pelajaran dan Jadwal Pelajaran Mingguan (matriks rombel × hari × slot).
 */
class LessonScheduleController extends Controller
{
    public const array WEEKDAYS = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];

    public function __construct(private readonly LessonScheduleService $service) {}

    public function periods(Request $request): Response
    {
        $unitId = $request->integer('unit') ?: null;
        $search = trim($request->string('search')->toString());

        $paginator = LessonPeriod::query()
            ->with('unit:id,name')
            ->when($unitId !== null, fn (Builder $q) => $q->where('unit_id', $unitId))
            ->when($search !== '', fn (Builder $q) => $q->whereLike('name', "%{$search}%"))
            ->orderBy('unit_id')->orderBy('sort_order')->orderBy('starts_at')->orderBy('id')
            ->paginate(50)->withQueryString();

        return Inertia::render('admin/academic/lesson-periods', [
            'rows' => [
                'data' => array_values(array_map(fn (LessonPeriod $p): array => [
                    'id' => $p->id, 'unit_id' => $p->unit_id, 'unit_name' => $p->unit->name, 'name' => $p->name,
                    'starts_at' => $p->starts_at, 'ends_at' => $p->ends_at, 'sort_order' => $p->sort_order, 'is_break' => $p->is_break,
                ], $paginator->items())),
                'meta' => [
                    'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(), 'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(),
                ],
            ],
            'filters' => ['search' => $search, 'unit' => $unitId],
            'unitOptions' => $this->unitOptions(),
        ]);
    }

    public function storePeriod(Request $request): RedirectResponse
    {
        $this->service->savePeriod(null, $this->periodData($request, null), $this->actor($request));

        return $this->done('Slot jam pelajaran ditambahkan.');
    }

    public function updatePeriod(Request $request, LessonPeriod $period): RedirectResponse
    {
        $this->service->savePeriod($period, $this->periodData($request, $period), $this->actor($request));

        return $this->done('Slot jam pelajaran diperbarui.');
    }

    public function destroyPeriod(Request $request, LessonPeriod $period): RedirectResponse
    {
        $this->service->deletePeriod($period, $this->actor($request));

        return $this->done('Slot jam pelajaran dihapus.');
    }

    public function schedule(Request $request): Response
    {
        $context = SchoolContext::resolve($request->session());
        $units = Unit::query()->where('is_active', true)->orderBy('id')->get(['id', 'code', 'name']);
        $unit = $units->firstWhere('id', $request->integer('unit')) ?? $units->firstWhere('code', $context['unit']) ?? $units->first();
        $yearId = AcademicYear::query()->where('is_active', true)->value('id');

        $classrooms = $unit === null || $yearId === null ? collect() : Classroom::query()
            ->where('unit_id', $unit->id)->where('academic_year_id', $yearId)->where('is_active', true)
            ->orderBy('grade')->orderBy('name')->get(['id', 'grade', 'name']);
        $classroom = $classrooms->firstWhere('id', $request->integer('classroom')) ?? $classrooms->first();

        $periods = $unit === null ? collect() : LessonPeriod::query()->where('unit_id', $unit->id)
            ->orderBy('sort_order')->orderBy('starts_at')->get();

        $cells = $classroom === null ? [] : LessonSchedule::query()
            ->with(['subject:id,name,code', 'teacher:id,name', 'room:id,name'])
            ->where('classroom_id', $classroom->id)
            ->orderBy('weekday')->orderBy('lesson_period_id')
            ->get()
            ->map(fn (LessonSchedule $s): array => [
                'weekday' => $s->weekday, 'period_id' => $s->lesson_period_id,
                'subject_id' => $s->subject_id, 'subject' => $s->subject->name,
                'teacher_id' => $s->teacher_id, 'teacher' => $s->teacher->name,
                'room_id' => $s->room_id, 'room' => $s->room?->name,
            ])->values()->all();

        return Inertia::render('admin/academic/lesson-schedule', [
            'weekdays' => array_map(fn (int $k, string $v): array => ['value' => $k, 'label' => $v], array_keys(self::WEEKDAYS), self::WEEKDAYS),
            'unitOptions' => $units->map(fn (Unit $u): array => ['value' => (string) $u->id, 'label' => $u->name])->values()->all(),
            'unitId' => $unit?->id,
            'classroomOptions' => $classrooms->map(fn (Classroom $c): array => ['value' => (string) $c->id, 'label' => $c->name])->values()->all(),
            'classroomId' => $classroom?->id,
            'classroomName' => $classroom?->name,
            'periods' => $periods->map(fn (LessonPeriod $p): array => [
                'id' => $p->id, 'name' => $p->name, 'starts_at' => $p->starts_at, 'ends_at' => $p->ends_at, 'is_break' => $p->is_break,
            ])->values()->all(),
            'cells' => $cells,
            'subjectOptions' => $unit === null ? [] : Subject::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code'])
                ->map(fn (Subject $s): array => ['value' => (string) $s->id, 'label' => "{$s->name} ({$s->code})"])->values()->all(),
            'teacherOptions' => User::query()->role(Role::Teacher->value)->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $u): array => ['value' => (string) $u->id, 'label' => $u->name])->values()->all(),
            'roomOptions' => $unit === null ? [] : Room::query()->whereHas('building', fn (Builder $q) => $q->where('unit_id', $unit->id))->orderBy('name')->get(['id', 'name'])
                ->map(fn (Room $r): array => ['value' => (string) $r->id, 'label' => $r->name])->values()->all(),
        ]);
    }

    public function plot(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'classroom_id' => ['required', 'integer', Rule::exists('classrooms', 'id')],
            'parallel_ids' => ['array', 'max:10'],
            'parallel_ids.*' => ['integer', 'distinct'],
            'weekday' => ['required', 'integer', 'between:1,6'],
            'lesson_period_id' => ['required', 'integer'],
            'subject_id' => ['required', 'integer'],
            'teacher_id' => ['required', 'integer'],
            'room_id' => ['nullable', 'integer'],
        ], [], ['subject_id' => 'mata pelajaran', 'teacher_id' => 'guru pengampu', 'room_id' => 'ruangan', 'lesson_period_id' => 'slot jam']);

        $classroom = Classroom::query()->findOrFail((int) $data['classroom_id']);
        $period = LessonPeriod::query()->where('unit_id', $classroom->unit_id)->findOrFail((int) $data['lesson_period_id']);
        Subject::query()->where('unit_id', $classroom->unit_id)->findOrFail((int) $data['subject_id']);
        User::query()->role(Role::Teacher->value)->where('is_active', true)->findOrFail((int) $data['teacher_id']);
        $roomId = isset($data['room_id']) ? (int) $data['room_id'] : null;
        if ($roomId !== null) {
            Room::query()->whereHas('building', fn (Builder $q) => $q->where('unit_id', $classroom->unit_id))->findOrFail($roomId);
        }

        // Rombel paralel harus satu unit dan satu tahun ajaran dengan rombel utama.
        $parallel = Classroom::query()
            ->whereIn('id', array_map(intval(...), $data['parallel_ids'] ?? []))
            ->where('unit_id', $classroom->unit_id)
            ->where('academic_year_id', $classroom->academic_year_id)
            ->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all();

        $this->service->plot([$classroom->id, ...$parallel], (int) $data['weekday'], $period, (int) $data['subject_id'], (int) $data['teacher_id'], $roomId, $this->actor($request));

        return $this->done('Jadwal tersimpan.');
    }

    public function clear(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'classroom_id' => ['required', 'integer', Rule::exists('classrooms', 'id')],
            'weekday' => ['required', 'integer', 'between:1,6'],
            'lesson_period_id' => ['required', 'integer', Rule::exists('lesson_periods', 'id')],
        ]);

        $this->service->clear(
            Classroom::query()->findOrFail((int) $data['classroom_id']),
            (int) $data['weekday'],
            LessonPeriod::query()->findOrFail((int) $data['lesson_period_id']),
            $this->actor($request),
        );

        return $this->done('Sel jadwal dikosongkan.');
    }

    /**
     * @return array{unit_id: int, name: string, starts_at: string, ends_at: string, sort_order: int, is_break: bool}
     */
    private function periodData(Request $request, ?LessonPeriod $period): array
    {
        $data = $request->validate([
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:30', Rule::unique('lesson_periods', 'name')->where('unit_id', $request->integer('unit_id'))->ignore($period?->id)],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:99'],
            'is_break' => ['required', 'in:0,1'],
        ], [], ['unit_id' => 'unit', 'name' => 'nama slot', 'starts_at' => 'jam mulai', 'ends_at' => 'jam selesai', 'sort_order' => 'urutan', 'is_break' => 'jam istirahat']);

        return [
            'unit_id' => (int) $data['unit_id'],
            'name' => trim((string) $data['name']),
            'starts_at' => (string) $data['starts_at'],
            'ends_at' => (string) $data['ends_at'],
            'sort_order' => (int) $data['sort_order'],
            'is_break' => (string) $data['is_break'] === '1',
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function unitOptions(): array
    {
        return array_values(Unit::query()->where('is_active', true)->orderBy('id')->get(['id', 'name'])
            ->map(fn (Unit $u): array => ['value' => (string) $u->id, 'label' => $u->name])->all());
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
