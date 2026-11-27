<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\PibChapter;
use App\Models\PibMaterial;
use App\Models\PibScore;
use App\Models\PibSubchapter;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Quran\PibAssessmentService;
use App\Services\Quran\PibCurriculumService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PibController extends Controller
{
    public function __construct(
        private readonly PibCurriculumService $curriculum,
        private readonly PibAssessmentService $assessment,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor->can(Permission::QuranView->value), 403);

        $activeYear = AcademicYear::query()->where('is_active', true)->first();

        $chapterModels = PibChapter::query()
            ->with('subchapters.materials')
            ->orderBy('sort_order')
            ->get();

        $chapters = [];
        foreach ($chapterModels as $chapter) {
            $subchapters = [];
            foreach ($chapter->subchapters as $subchapter) {
                $materials = [];
                foreach ($subchapter->materials as $material) {
                    $materials[] = [
                        'id' => $material->id,
                        'name' => $material->name,
                        'weight' => $material->weight,
                    ];
                }
                $subchapters[] = [
                    'id' => $subchapter->id,
                    'name' => $subchapter->name,
                    'materials' => $materials,
                ];
            }
            $chapters[] = [
                'id' => $chapter->id,
                'name' => $chapter->name,
                'subchapters' => $subchapters,
            ];
        }

        $classrooms = $activeYear !== null
            ? Classroom::query()
                ->with('unit:id,code')
                ->where('academic_year_id', $activeYear->id)
                ->where('is_active', true)
                ->orderBy('grade')
                ->orderBy('name')
                ->get(['id', 'unit_id', 'grade', 'name'])
            : collect();

        $selectedClassroomId = $request->filled('classroom_id')
            ? $request->integer('classroom_id')
            : $classrooms->first()?->id;

        $selectedMaterialId = $request->filled('material_id')
            ? $request->integer('material_id')
            : PibMaterial::query()->orderBy('id')->value('id');

        $students = [];
        if ($selectedClassroomId !== null && $activeYear !== null) {
            $enrollments = Enrollment::query()
                ->with('student:id,nis,name')
                ->where('classroom_id', $selectedClassroomId)
                ->where('academic_year_id', $activeYear->id)
                ->get();

            $scoresByStudent = $selectedMaterialId !== null
                ? PibScore::query()
                    ->where('pib_material_id', $selectedMaterialId)
                    ->where('academic_year_id', $activeYear->id)
                    ->whereIn('student_id', $enrollments->pluck('student_id'))
                    ->get()
                    ->keyBy('student_id')
                : collect();

            $students = $enrollments
                ->sortBy(fn (Enrollment $e): string => $e->student->name)
                ->map(function (Enrollment $e) use ($scoresByStudent): array {
                    /** @var PibScore|null $s */
                    $s = $scoresByStudent->get($e->student_id);

                    return [
                        'student_id' => $e->student_id,
                        'nis' => $e->student->nis,
                        'name' => $e->student->name,
                        'mistakes' => $s?->mistakes,
                        'score' => $s?->score,
                    ];
                })
                ->values()
                ->all();
        }

        return Inertia::render('admin/quran/pib', [
            'chapters' => $chapters,
            'classrooms' => $classrooms->map(fn (Classroom $c): array => [
                'id' => $c->id,
                'label' => ($c->unit->code ?? '').' · '.$c->name,
            ])->values()->all(),
            'units' => Unit::query()->orderBy('name')->get(['id', 'code', 'name']),
            'selectedClassroomId' => $selectedClassroomId,
            'selectedMaterialId' => $selectedMaterialId !== null ? (int) $selectedMaterialId : null,
            'students' => $students,
            'canManage' => $actor->can(Permission::QuranManage->value),
            'canRecord' => $actor->can(Permission::QuranRecord->value),
        ]);
    }

    public function storeChapter(Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor->can(Permission::QuranManage->value), 403);

        $validated = $request->validate([
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'name' => ['required', 'string', 'max:150'],
        ]);

        $this->curriculum->createChapter(
            $actor,
            isset($validated['unit_id']) ? (int) $validated['unit_id'] : null,
            (string) $validated['name'],
        );

        return back()->with('success', 'Bab PIB berhasil ditambahkan.');
    }

    public function storeSubchapter(Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor->can(Permission::QuranManage->value), 403);

        $validated = $request->validate([
            'pib_chapter_id' => ['required', 'integer', 'exists:pib_chapters,id'],
            'name' => ['required', 'string', 'max:150'],
        ]);

        $chapter = PibChapter::query()->findOrFail((int) $validated['pib_chapter_id']);
        $this->curriculum->createSubchapter($actor, $chapter, (string) $validated['name']);

        return back()->with('success', 'Subbab PIB berhasil ditambahkan.');
    }

    public function storeMaterial(Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor->can(Permission::QuranManage->value), 403);

        $validated = $request->validate([
            'pib_subchapter_id' => ['required', 'integer', 'exists:pib_subchapters,id'],
            'name' => ['required', 'string', 'max:150'],
            'weight' => ['required', 'numeric', 'min:0.1', 'max:100'],
        ]);

        $sub = PibSubchapter::query()->findOrFail((int) $validated['pib_subchapter_id']);
        $this->curriculum->createMaterial($actor, $sub, (string) $validated['name'], (float) $validated['weight']);

        return back()->with('success', 'Materi PIB berhasil ditambahkan.');
    }

    public function storeScore(Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor->can(Permission::QuranRecord->value), 403);

        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'pib_material_id' => ['required', 'integer', 'exists:pib_materials,id'],
            'mistakes' => ['required', 'integer', 'min:0', 'max:90'],
        ]);

        $year = AcademicYear::query()->where('is_active', true)->first();
        if ($year === null) {
            throw ValidationException::withMessages(['student_id' => 'Belum ada tahun ajaran aktif.']);
        }

        $student = Student::query()->findOrFail((int) $validated['student_id']);
        $material = PibMaterial::query()->findOrFail((int) $validated['pib_material_id']);

        $score = $this->assessment->recordScore($actor, $student, $material, $year, (int) $validated['mistakes']);

        return back()->with('success', "Nilai PIB {$student->name}: {$score->score} ({$score->mistakes} salah).");
    }
}
