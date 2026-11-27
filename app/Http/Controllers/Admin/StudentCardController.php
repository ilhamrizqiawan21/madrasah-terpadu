<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RegisterRfidCardRequest;
use App\Models\Student;
use App\Models\User;
use App\Services\Attendance\StudentCardService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class StudentCardController extends Controller
{
    public function __construct(private readonly StudentCardService $service) {}

    public function issueQr(Request $request, Student $student): RedirectResponse
    {
        Gate::authorize('update', $student);

        $issued = $this->service->issueQr($student, $this->actor($request));

        Inertia::flash([
            'toast' => [
                'type' => 'success',
                'message' => "Kartu QR {$student->name} diterbitkan.",
            ],
            'issuedCardToken' => [
                'student_id' => $student->id,
                'token' => $issued['token'],
            ],
        ]);

        return back();
    }

    public function registerRfid(RegisterRfidCardRequest $request, Student $student): RedirectResponse
    {
        Gate::authorize('update', $student);

        try {
            $this->service->registerRfid($student, $request->string('uid')->toString(), $this->actor($request));
        } catch (DomainException $exception) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);

            return back()->withErrors(['uid' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Kartu RFID {$student->name} berhasil didaftarkan.",
        ]);

        return back();
    }

    public function revoke(Request $request, Student $student): RedirectResponse
    {
        Gate::authorize('update', $student);

        $revokedCount = $this->service->revokeActive($student, $this->actor($request));

        Inertia::flash('toast', [
            'type' => $revokedCount > 0 ? 'success' : 'info',
            'message' => $revokedCount > 0
                ? "Kartu aktif {$student->name} dicabut."
                : "{$student->name} tidak memiliki kartu aktif.",
        ]);

        return back();
    }

    private function actor(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
