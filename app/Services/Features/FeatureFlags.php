<?php

declare(strict_types=1);

namespace App\Services\Features;

use App\Enums\Feature;
use App\Models\FeatureFlag;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Satu-satunya pintu baca/tulis kunci fitur (T-211). Dibaca di setiap request
 * (menu + middleware), jadi status tersimpan di-cache dan cache dibuang setelah commit.
 */
final class FeatureFlags
{
    private const CACHE_KEY = 'feature_flags.values';

    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function enabled(Feature $feature): bool
    {
        return $this->all()[$feature->value];
    }

    /**
     * Status setiap modul: nilai tersimpan, atau bawaan dari enum bila belum pernah diubah.
     *
     * @return array<string, bool>
     */
    public function all(): array
    {
        /** @var array<string, bool> $stored */
        $stored = Cache::rememberForever(self::CACHE_KEY, fn (): array => FeatureFlag::query()
            ->orderBy('key')
            ->get(['key', 'enabled'])
            ->mapWithKeys(fn (FeatureFlag $flag): array => [$flag->key => $flag->enabled])
            ->all());

        $values = [];

        foreach (Feature::cases() as $feature) {
            $values[$feature->value] = $stored[$feature->value] ?? $feature->defaultEnabled();
        }

        return $values;
    }

    /**
     * Simpan status beberapa modul dalam satu transaksi + satu baris audit berisi modul yang berubah.
     *
     * @param  array<string, bool>  $values
     */
    public function update(array $values, User $actor): void
    {
        foreach (array_keys($values) as $key) {
            if (Feature::tryFrom($key) === null) {
                throw new InvalidArgumentException("Kunci fitur [{$key}] tidak dikenal.");
            }
        }

        ksort($values);

        DB::transaction(function () use ($values, $actor): void {
            $stored = FeatureFlag::query()
                ->whereIn('key', array_keys($values))
                ->orderBy('key')
                ->lockForUpdate()
                ->get()
                ->keyBy('key');

            $before = [];
            $after = [];

            foreach ($values as $key => $enabled) {
                $current = $stored->get($key)->enabled ?? Feature::from($key)->defaultEnabled();

                if ($current === $enabled) {
                    continue;
                }

                $before[$key] = $current;
                $after[$key] = $enabled;
            }

            if ($after === []) {
                return;
            }

            $now = now();

            FeatureFlag::query()->upsert(
                array_map(fn (string $key): array => [
                    'key' => $key,
                    'enabled' => $after[$key],
                    'updated_by' => $actor->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], array_keys($after)),
                uniqueBy: ['key'],
                update: ['enabled', 'updated_by', 'updated_at'],
            );

            $this->auditLogger->record(
                action: 'feature_flags_updated',
                subject: FeatureFlag::class,
                before: $before,
                after: $after,
                userId: $actor->id,
            );

            DB::afterCommit(fn () => Cache::forget(self::CACHE_KEY));
        });
    }
}
