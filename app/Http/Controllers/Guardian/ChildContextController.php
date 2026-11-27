<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\GuardianContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChildContextController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer'],
        ]);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        if (! GuardianContext::select($user, $request->session(), (int) $data['student_id'])) {
            abort(404);
        }

        return back();
    }
}
