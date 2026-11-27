<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $guardian = $user->guardian;

        return Inertia::render('guardian/account', [
            'profile' => [
                'name' => $guardian->name ?? $user->name,
                'phone' => $guardian->phone ?? $user->phone,
                'relation' => $guardian?->relation->label() ?? null,
            ],
        ]);
    }
}
