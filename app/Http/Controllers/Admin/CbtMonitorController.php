<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\User;
use App\Services\Cbt\ExamMonitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CbtMonitorController extends Controller
{
    public function __construct(private readonly ExamMonitorService $service) {}

    public function index(Request $request, Exam $exam): Response
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('view', $exam), 404);

        $exam->load('subject');

        return Inertia::render('admin/cbt/monitor/index', [
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'subject' => $exam->subject->name,
                'status_label' => $exam->status->label(),
            ],
            'canControl' => $actor->can('update', $exam),
            'initialData' => $this->service->snapshot($exam),
        ]);
    }

    public function data(Request $request, Exam $exam): JsonResponse
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('view', $exam), 404);

        return response()->json($this->service->snapshot($exam));
    }

    public function unlock(Request $request, Exam $exam, ExamSession $session): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('update', $exam), 404);
        abort_unless($session->exam_id === $exam->id, 404);

        $this->service->unlock($session, $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sesi ujian siswa berhasil dibuka kembali.']);

        return back();
    }

    public function extraTime(Request $request, Exam $exam, ExamSession $session): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('update', $exam), 404);
        abort_unless($session->exam_id === $exam->id, 404);

        $validated = $request->validate([
            'minutes' => ['required', 'integer', 'min:1', 'max:120'],
        ]);

        $this->service->addExtraTime($session, (int) $validated['minutes'], $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Tambahan waktu {$validated['minutes']} menit diberikan."]);

        return back();
    }

    public function forceClose(Request $request, Exam $exam, ExamSession $session): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('update', $exam), 404);
        abort_unless($session->exam_id === $exam->id, 404);

        $this->service->forceClose($session, $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sesi ujian siswa dihentikan dan dijadwalkan untuk dinilai.']);

        return back();
    }

    private function actor(Request $request): User
    {
        $user = $request->user('web');

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
