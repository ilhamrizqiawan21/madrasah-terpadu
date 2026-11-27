<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ExamQuestionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportExamEssayScoresRequest;
use App\Http\Requests\Admin\SaveExamEssayScoresRequest;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\User;
use App\Services\Cbt\ExamEssayScoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CbtEssayScoreController extends Controller
{
    public function __construct(private readonly ExamEssayScoreService $service) {}

    public function index(Request $request, Exam $exam): Response
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('update', $exam), 404);

        $exam->load('subject');
        $essayQuestions = $exam->questions()
            ->where('type', ExamQuestionType::EssayPaper)
            ->get(['id', 'sort_order', 'body']);

        $sessions = ExamSession::query()
            ->with(['student:id,nis,name', 'essayScores', 'result'])
            ->where('exam_id', $exam->id)
            ->orderBy('id')
            ->get()
            ->map(fn (ExamSession $s): array => [
                'id' => $s->id,
                'nis' => $s->student->nis,
                'name' => $s->student->name,
                'status_label' => $s->result?->status->label() ?? $s->status->label(),
                'final_score' => $s->result?->score,
                'essay_scores' => $s->essayScores->pluck('score', 'exam_question_id')->all(),
            ])
            ->values();

        return Inertia::render('admin/cbt/essay/index', [
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'subject' => $exam->subject->name,
                'max_essay_weight' => max(0, (int) ($exam->weights['essay_paper'] ?? 4)),
            ],
            'essayQuestions' => $essayQuestions->map(fn ($q): array => [
                'id' => $q->id,
                'sort_order' => $q->sort_order,
                'body' => $q->body,
            ])->values(),
            'sessions' => $sessions,
        ]);
    }

    public function store(SaveExamEssayScoresRequest $request, Exam $exam): RedirectResponse
    {
        $session = ExamSession::query()
            ->where('exam_id', $exam->id)
            ->findOrFail($request->integer('exam_session_id'));

        /** @var list<array{exam_question_id: int, score: int}> $scores */
        $scores = array_map(
            static fn (array $item): array => [
                'exam_question_id' => (int) $item['exam_question_id'],
                'score' => (int) $item['score'],
            ],
            (array) $request->validated('scores'),
        );

        $this->service->saveForSession($exam, $session, $scores, $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Nilai essay berhasil disimpan.']);

        return back();
    }

    public function import(ImportExamEssayScoresRequest $request, Exam $exam): RedirectResponse
    {
        $result = $this->service->importFromExcel($exam, $request->excelFile(), $this->actor($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$result['updated']} nilai essay berhasil diimpor.",
        ]);

        return back();
    }

    private function actor(Request $request): User
    {
        $user = $request->user('web');

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
