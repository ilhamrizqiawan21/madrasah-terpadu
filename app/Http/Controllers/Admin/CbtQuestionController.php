<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ExamQuestionType;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CorrectAnswerKeyRequest;
use App\Http\Requests\Admin\ExamQuestionRequest;
use App\Http\Requests\Admin\ImportExamQuestionsRequest;
use App\Http\Resources\ExamQuestionResource;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\User;
use App\Services\Cbt\ExamQuestionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CbtQuestionController extends Controller
{
    public function __construct(private readonly ExamQuestionService $service) {}

    public function index(Request $request, Exam $exam): Response
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('view', $exam), 404);

        $exam->load('subject');
        $questions = $exam->questions()->with('options')->get();

        return Inertia::render('admin/cbt/questions/index', [
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'subject' => $exam->subject->code.' — '.$exam->subject->name,
                'is_locked' => $exam->isLocked(),
                'status' => $exam->status->value,
                'status_label' => $exam->status->label(),
            ],
            'canEdit' => $actor->can('update', $exam) && ! $exam->isLocked(),
            'canCorrectKey' => $actor->can(Permission::CbtManage->value),
            'questions' => ExamQuestionResource::collection($questions)->resolve(),
            'questionTypeOptions' => ExamQuestionType::options(),
        ]);
    }

    public function store(ExamQuestionRequest $request, Exam $exam): RedirectResponse
    {
        $this->service->create($exam, $request->questionData(), $request->imageFile(), $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Soal berhasil ditambahkan.']);

        return back();
    }

    public function update(ExamQuestionRequest $request, Exam $exam, ExamQuestion $question): RedirectResponse
    {
        abort_unless($question->exam_id === $exam->id, 404);

        $this->service->update(
            $exam,
            $question,
            $request->questionData(),
            $request->imageFile(),
            $request->boolean('remove_image'),
            $this->actor($request),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Soal berhasil diperbarui.']);

        return back();
    }

    public function destroy(Request $request, Exam $exam, ExamQuestion $question): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('update', $exam), 404);
        abort_if($exam->isLocked(), 403, 'Soal terkunci setelah ujian dimulai.');
        abort_unless($question->exam_id === $exam->id, 404);

        $this->service->delete($exam, $question, $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Soal berhasil dihapus.']);

        return back();
    }

    public function correctKey(CorrectAnswerKeyRequest $request, Exam $exam, ExamQuestion $question): RedirectResponse
    {
        abort_unless($question->exam_id === $exam->id, 404);

        /** @var array<string, mixed> $answerKey */
        $answerKey = $request->validated('answer_key');

        $this->service->correctAnswerKey(
            $question,
            $answerKey,
            (string) $request->validated('reason'),
            $this->actor($request),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kunci jawaban berhasil dikoreksi.']);

        return back();
    }

    public function import(ImportExamQuestionsRequest $request, Exam $exam): RedirectResponse
    {
        $result = $this->service->importFromExcel($exam, $request->excelFile(), $this->actor($request));

        $message = "{$result['created']} soal berhasil diimpor";
        if ($result['failed'] !== []) {
            $message .= ', '.count($result['failed']).' baris gagal.';
        } else {
            $message .= '.';
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    public function image(Request $request, Exam $exam, ExamQuestion $question): StreamedResponse
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('view', $exam), 404);
        abort_unless($question->exam_id === $exam->id && $question->image_path !== null, 404);

        $disk = Storage::disk(ExamQuestionService::DISK);
        abort_unless($disk->exists($question->image_path), 404);

        return $disk->response($question->image_path);
    }

    private function actor(Request $request): User
    {
        $user = $request->user('web');

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
