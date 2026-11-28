<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Domain\Payments\MidtransSignature;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessMidtransNotification;
use App\Models\PaymentNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Penerima HTTP Notification Midtrans (T-703, PAYMENTS §4). Tanpa CSRF dan sesi pengguna;
 * keaslian dibuktikan signature, isinya dikonfirmasi ulang lewat Get Status di job.
 */
class MidtransWebhookController extends Controller
{
    public function __invoke(Request $request, MidtransSignature $signature): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        $valid = $signature->isValid($payload);
        $orderId = is_string($payload['order_id'] ?? null) ? Str::limit($payload['order_id'], 64, '') : null;

        $notification = PaymentNotification::query()->create([
            'order_id' => $orderId,
            'transaction_status' => is_string($payload['transaction_status'] ?? null) ? Str::limit($payload['transaction_status'], 30, '') : null,
            'status_code' => is_string($payload['status_code'] ?? null) ? Str::limit($payload['status_code'], 5, '') : null,
            'signature_valid' => $valid,
            'payload' => $payload,
            'received_at' => now(),
        ]);

        if (! $valid) {
            // Jangan catat payload lengkap: cukup penanda untuk penelusuran.
            Log::warning('Webhook Midtrans dengan signature tidak valid ditolak.', [
                'notification_id' => $notification->id,
                'order_id' => $orderId,
                'ip' => $request->ip(),
            ]);

            return response()->json(['status' => 'invalid signature'], 403);
        }

        ProcessMidtransNotification::dispatch($notification->id);

        return response()->json(['status' => 'ok']);
    }
}
