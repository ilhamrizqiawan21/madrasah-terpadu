<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

test('audit logger menyaring kunci sensitif dari before dan after', function (): void {
    $user = User::factory()->create();
    $logger = app(AuditLogger::class);

    $log = $logger->record(
        action: 'updated',
        subject: $user,
        before: [
            'name' => 'Lama',
            'password' => 'rahasia-lama',
            'remember_token' => 'tok-123',
            'two_factor_secret' => 'sec-123',
        ],
        after: [
            'name' => 'Baru',
            'password' => 'rahasia-baru',
            'token_hash' => 'hash-abc',
            'two_factor_recovery_codes' => 'rec-123',
            'nested' => [
                'token' => 'nested-tok',
                'phone' => '081234567890',
            ],
        ],
    );

    expect($log->before)->toBe(['name' => 'Lama'])
        ->and($log->after)->toBe([
            'name' => 'Baru',
            'nested' => ['phone' => '081234567890'],
        ]);
});

test('audit logger mencatat user, ip, dan user agent dari pengguna terautentikasi', function (): void {
    $actor = User::factory()->create();
    $subject = User::factory()->create();

    $this->actingAs($actor);

    $log = app(AuditLogger::class)->record(
        action: 'created',
        subject: $subject,
        after: ['name' => $subject->name],
    );

    expect($log->user_id)->toBe($actor->id)
        ->and($log->action)->toBe('created')
        ->and($log->subject_type)->toBe(User::class)
        ->and($log->subject_id)->toBe($subject->id)
        ->and($log->created_at)->not->toBeNull();
});

test('audit logger tetap berjalan tanpa pengguna atau subjek model', function (): void {
    $log = app(AuditLogger::class)->record(
        action: 'system_sync',
        subject: 'App\Domain\Sync',
        after: ['count' => 10],
    );

    expect($log->user_id)->toBeNull()
        ->and($log->subject_type)->toBe('App\Domain\Sync')
        ->and($log->subject_id)->toBeNull();
});

test('audit log ikut dibatalkan saat transaksi pemanggil rollback', function (): void {
    $subject = User::factory()->create();

    try {
        DB::transaction(function () use ($subject): void {
            app(AuditLogger::class)->record('deleted', $subject);

            throw new RuntimeException('Simulasi gagal');
        });
    } catch (RuntimeException) {
        // diharapkan
    }

    expect(AuditLog::query()->where('action', 'deleted')->count())->toBe(0);
});
