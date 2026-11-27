<?php

declare(strict_types=1);

namespace App\Http\Controllers\Exam;

use App\Domain\Exam\SessionClock;
use App\Enums\ExamSessionStatus;
use App\Exceptions\Cbt\DeviceTakeoverException;
use App\Exceptions\Cbt\ExamAccessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Exam\StartExamSessionRequest;
use App\Http\Requests\Exam\SyncExamAnswersRequest;
use App\Http\Resources\StudentExamQuestionResource;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Student;
use App\Services\Cbt\ExamSessionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ExamSessionController extends Controller
{
    public function __construct(private readonly ExamSessionService $service) {}

    public function start(StartExamSessionRequest $request, Exam $exam): RedirectResponse
    {
        $student = $this->student();

        try {
            $session = $this->service->startOrResume(
                $exam,
                $student,
                (string) $request->validated('device_type'),
                $request->validated('access_token'),
            );
        } catch (ExamAccessException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        $request->session()->put($this->deviceSessionKey($exam), $session->device_token);

        return redirect()->route('exam.sessions.show', $exam);
    }

    public function show(Request $request, Exam $exam): Response|RedirectResponse
    {
        $student = $this->student();
        $session = ExamSession::query()
            ->where('exam_id', $exam->id)
            ->where('student_id', $student->id)
            ->first();

        if (! $session instanceof ExamSession || $session->status !== ExamSessionStatus::InProgress) {
            return redirect()->route('exam.index');
        }

        $exam->load('subject');
        $questions = $this->service->orderedQuestions($session);
        $savedAnswers = $session->answers()
            ->get(['exam_question_id', 'answer', 'is_flagged'])
            ->keyBy('exam_question_id')
            ->map(fn ($ans): array => [
                'answer' => $ans->answer,
                'is_flagged' => $ans->is_flagged,
            ]);

        $now = CarbonImmutable::now();

        return Inertia::render('exam/session', [
            'student' => [
                'id' => $student->id,
                'nis' => $student->nis,
                'name' => $student->name,
            ],
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'subject' => $exam->subject->name,
                'violation_limit' => $exam->violation_limit,
            ],
            'session' => [
                'id' => $session->id,
                'device_type' => $session->device_type,
                'device_token' => (string) $request->session()->get($this->deviceSessionKey($exam), $session->device_token),
                'remaining_seconds' => SessionClock::remainingSeconds($now, $session->deadline_at, $session->extra_minutes),
            ],
            'questions' => StudentExamQuestionResource::collection($questions)->resolve(),
            'savedAnswers' => $savedAnswers,
        ]);
    }

    public function syncAnswers(SyncExamAnswersRequest $request, Exam $exam): JsonResponse
    {
        $student = $this->student();
        $session = ExamSession::query()
            ->where('exam_id', $exam->id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        /** @var list<array{exam_question_id: int, answer: array<string, mixed>|null, is_flagged: bool, client_ts: string}> $answers */
        $answers = $request->validated('answers');

        try {
            $result = $this->service->syncAnswers(
                $session,
                (string) $request->validated('device_token'),
                $answers,
            );
        } catch (DeviceTakeoverException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (ExamAccessException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    public function submit(Request $request, Exam $exam): RedirectResponse
    {
        $student = $this->student();
        $session = ExamSession::query()
            ->where('exam_id', $exam->id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        $deviceToken = (string) $request->input(
            'device_token',
            $request->session()->get($this->deviceSessionKey($exam), ''),
        );

        try {
            $this->service->submit($session, $deviceToken);
        } catch (DeviceTakeoverException $e) {
            return redirect()->route('exam.index')->withErrors(['exam' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Jawaban ujian berhasil dikumpulkan.']);

        return redirect()->route('exam.index');
    }

    private function student(): Student
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        return $student;
    }

    private function deviceSessionKey(Exam $exam): string
    {
        return 'exam_device_token_'.$exam->id;
    }
}
