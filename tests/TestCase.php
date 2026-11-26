<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureSafeTestingDatabase();
    }

    protected function ensureSafeTestingDatabase(): void
    {
        $connection = (string) config('database.default');

        if ($connection === 'sqlite') {
            return;
        }

        $database = (string) config("database.connections.{$connection}.database", '');

        if (! str_ends_with($database, '_testing')) {
            throw new RuntimeException(
                "Pengujian dengan koneksi [{$connection}] ditolak karena nama database [{$database}] tidak berakhiran '_testing'."
            );
        }
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
