<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

test('guard menolak pengujian non-sqlite jika nama database tidak berakhiran _testing', function (): void {
    $originalDefault = config('database.default');
    $originalDatabase = config('database.connections.mysql.database');

    try {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => 'madrasah_terpadu',
        ]);

        expect(fn () => $this->ensureSafeTestingDatabase())
            ->toThrow(RuntimeException::class, '_testing');
    } finally {
        config([
            'database.default' => $originalDefault,
            'database.connections.mysql.database' => $originalDatabase,
        ]);
    }
});
