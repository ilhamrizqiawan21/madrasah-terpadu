<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kotak masuk notifikasi wali murid (T-402).
 */
class NotificationInboxController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $paginator = $user->notifications()
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('guardian/notifications', [
            'notifications' => [
                'data' => $paginator->getCollection()->map(function (DatabaseNotification $item): array {
                    $payload = $item->data;

                    return [
                        'id' => $item->id,
                        'title' => is_string($payload['title'] ?? null) ? $payload['title'] : 'Pemberitahuan',
                        'body' => is_string($payload['body'] ?? null) ? $payload['body'] : '',
                        'url' => is_string($payload['url'] ?? null) ? $payload['url'] : null,
                        'category' => is_string($payload['category'] ?? null) ? $payload['category'] : null,
                        'read_at' => $item->read_at?->toIso8601String(),
                        'created_at' => $item->created_at?->toIso8601String(),
                    ];
                })->values(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ],
        ]);
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $record = $user->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $user->unreadNotifications()->update(['read_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Semua notifikasi ditandai sudah dibaca.']);

        return back();
    }
}
