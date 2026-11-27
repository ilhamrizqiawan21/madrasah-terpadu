<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TeachingAssignmentRequest;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\Cbt\TeachingAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CbtTeachingAssignmentController extends Controller
{
    public function __construct(private readonly TeachingAssignmentService $service) {}

    public function store(TeachingAssignmentRequest $request): RedirectResponse
    {
        $this->service->create($request->assignmentData(), $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Penugasan guru berhasil ditambahkan.']);

        return back();
    }

    public function destroy(Request $request, TeachingAssignment $assignment): RedirectResponse
    {
        $this->service->delete($assignment, $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Penugasan guru berhasil dihapus.']);

        return back();
    }

    private function actor(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
