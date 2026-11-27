<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Notifications\NotificationPreferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationPreferenceController extends Controller
{
    public function __construct(private readonly NotificationPreferenceService $service) {}

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $data = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*' => ['required', 'boolean'],
        ]);

        /** @var array<string, bool> $prefs */
        $prefs = array_map(static fn (mixed $v): bool => (bool) $v, $data['preferences']);

        $this->service->update($user, $prefs);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Preferensi notifikasi disimpan.']);

        return back();
    }
}
