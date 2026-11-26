<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Satu-satunya pintu penulisan audit log.
 * Panggil di dalam transaksi yang sama dengan perubahan datanya.
 */
final class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        string $action,
        Model|string $subject,
        ?array $before = null,
        ?array $after = null,
        ?int $userId = null,
    ): AuditLog {
        $subjectType = $subject instanceof Model ? $subject::class : $subject;
        $subjectId = null;

        if ($subject instanceof Model) {
            $key = $subject->getKey();
            if (is_int($key) || (is_string($key) && ctype_digit($key))) {
                $subjectId = (int) $key;
            }
        }

        $request = app()->bound('request') ? request() : null;
        $ip = $request instanceof Request ? $request->ip() : null;
        $userAgent = $request instanceof Request ? $request->userAgent() : null;

        return AuditLog::query()->create([
            'user_id' => $userId ?? Auth::id(),
            'action' => Str::limit($action, 32, ''),
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'before' => $this->sanitize($before),
            'after' => $this->sanitize($after),
            'ip' => $ip,
            'user_agent' => $userAgent !== null && $userAgent !== '' ? Str::limit($userAgent, 255, '') : null,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<mixed>|null  $data
     * @return array<mixed>|null
     */
    private function sanitize(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $filtered = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                continue;
            }

            $filtered[$key] = is_array($value) ? $this->sanitize($value) : $value;
        }

        return $filtered;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = Str::lower($key);

        return $normalized === 'password'
            || $normalized === 'password_confirmation'
            || $normalized === 'current_password'
            || $normalized === 'remember_token'
            || str_starts_with($normalized, 'token')
            || str_starts_with($normalized, 'two_factor')
            || str_starts_with($normalized, 'secret');
    }
}
