<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\User;
use App\Support\ExcelCell;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CbtResultController extends Controller
{
    public function index(Request $request, Exam $exam): Response
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('view', $exam), 404);

        $exam->load('subject');
        $classroomId = $request->integer('classroom_id') ?: null;
        $activeYearId = AcademicYear::query()->where('is_active', true)->value('id');

        $classrooms = Classroom::query()
            ->whereKey($exam->classroom_ids)
            ->orderBy('name')
            ->get(['id', 'name']);

        $results = $this->buildRows($exam, $classroomId, $activeYearId);

        return Inertia::render('admin/cbt/results/index', [
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'subject' => $exam->subject->name,
            ],
            'filters' => [
                'classroom_id' => $classroomId,
            ],
            'classroomOptions' => $classrooms->map(fn (Classroom $c): array => [
                'id' => $c->id,
                'name' => $c->name,
            ])->values(),
            'results' => $results,
        ]);
    }

    public function export(Request $request, Exam $exam): BinaryFileResponse
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('view', $exam), 404);

        $classroomId = $request->integer('classroom_id') ?: null;
        $activeYearId = AcademicYear::query()->where('is_active', true)->value('id');
        $rows = $this->buildRows($exam, $classroomId, $activeYearId);

        $path = tempnam(sys_get_temp_dir(), 'cbt_results_').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues([
            'NIS',
            'Nama siswa',
            'Rombel',
            'Status hasil',
            'Poin otomatis',
            'Poin essay',
            'Poin maksimal',
            'Nilai akhir',
        ]));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues([
                ExcelCell::neutralize($row['nis']),
                ExcelCell::neutralize($row['name']),
                ExcelCell::neutralize($row['classroom']),
                ExcelCell::neutralize($row['status_label']),
                $row['auto_points'],
                $row['essay_points'] ?? '-',
                $row['max_points'],
                $row['score'] ?? '-',
            ]));
        }

        $writer->close();

        return response()
            ->download($path, "hasil-ujian-{$exam->id}.xlsx")
            ->deleteFileAfterSend(true);
    }

    /**
     * @return list<array{
     *     session_id: int,
     *     nis: string,
     *     name: string,
     *     classroom: string,
     *     status_label: string,
     *     auto_points: int,
     *     essay_points: int|null,
     *     max_points: int,
     *     score: int|null
     * }>
     */
    private function buildRows(Exam $exam, ?int $classroomId, ?int $activeYearId): array
    {
        $sessions = ExamSession::query()
            ->with([
                'student.enrollments' => fn ($q) => $q
                    ->when($activeYearId !== null, fn ($inner) => $inner->where('academic_year_id', $activeYearId))
                    ->with('classroom:id,name'),
                'result',
            ])
            ->where('exam_id', $exam->id)
            ->when($classroomId !== null, function (Builder $q) use ($classroomId, $activeYearId): void {
                $q->whereHas('student.enrollments', function (Builder $inner) use ($classroomId, $activeYearId): void {
                    $inner->where('classroom_id', $classroomId)
                        ->when($activeYearId !== null, fn (Builder $y) => $y->where('academic_year_id', $activeYearId));
                });
            })
            ->orderBy('id')
            ->get();

        return array_values($sessions->map(function (ExamSession $session): array {
            $classroomName = $session->student->enrollments->first()?->classroom->name ?? '—';

            return [
                'session_id' => $session->id,
                'nis' => $session->student->nis,
                'name' => $session->student->name,
                'classroom' => $classroomName,
                'status_label' => $session->result?->status->label() ?? $session->status->label(),
                'auto_points' => $session->result->auto_points ?? 0,
                'essay_points' => $session->result?->essay_points,
                'max_points' => $session->result->max_points ?? 0,
                'score' => $session->result?->score,
            ];
        })->all());
    }

    private function actor(Request $request): User
    {
        $user = $request->user('web');

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
