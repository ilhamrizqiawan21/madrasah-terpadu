<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Http\Resources\GuardianHomeResource;
use App\Models\User;
use App\Services\Guardian\GuardianHomeSummaryService;
use App\Support\GuardianContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(private readonly GuardianHomeSummaryService $summary) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $student = GuardianContext::activeStudent($user, $request->session());

        return Inertia::render('guardian/home', [
            'home' => $student !== null
                ? (new GuardianHomeResource($this->summary->forStudent($student)))->resolve($request)
                : null,
        ]);
    }
}
