<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|Response
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasAnyRole([
            Role::Admin->value,
            Role::Treasurer->value,
            Role::Teacher->value,
            Role::Counselor->value,
            Role::UnitHead->value,
        ])) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->hasRole(Role::GateOfficer->value)) {
            return redirect()->route('gate.index');
        }

        if ($user->hasRole(Role::CanteenCashier->value)) {
            return redirect()->route('canteen.index');
        }

        if ($user->hasRole(Role::Guardian->value)) {
            return redirect()->route('guardian.home');
        }

        return Inertia::render('no-access');
    }
}
