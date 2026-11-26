<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;

test('lockForUpdate dapat dijalankan dalam transaksi', function (): void {
    $user = User::factory()->create(['name' => 'Sebelum Kunci']);

    DB::transaction(function () use ($user): void {
        $locked = User::query()
            ->whereKey($user->id)
            ->lockForUpdate()
            ->firstOrFail();

        $locked->update(['name' => 'Sesudah Kunci']);
    });

    expect($user->fresh()->name)->toBe('Sesudah Kunci');
})->group('mysql');
