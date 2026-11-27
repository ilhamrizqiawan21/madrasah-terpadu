<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PushSubscriptionRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Menyimpan dan menghapus Web Push subscription per perangkat pengguna login (T-401).
 */
class PushSubscriptionController extends Controller
{
    public function store(PushSubscriptionRequest $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        /** @var array{endpoint: string, keys?: array{p256dh?: ?string, auth?: ?string}, content_encoding?: ?string} $data */
        $data = $request->validated();

        $user->updatePushSubscription(
            endpoint: $data['endpoint'],
            key: $data['keys']['p256dh'] ?? null,
            token: $data['keys']['auth'] ?? null,
            contentEncoding: $data['content_encoding'] ?? null,
        );

        return response()->noContent();
    }

    public function destroy(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:500'],
        ]);

        $user->deletePushSubscription((string) $data['endpoint']);

        return response()->noContent();
    }
}
