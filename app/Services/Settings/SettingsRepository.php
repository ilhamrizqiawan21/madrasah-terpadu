<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Enums\SettingKey;
use App\Models\Setting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Satu-satunya pintu baca/tulis tabel `settings` (T-201).
 * Semua nilai dibaca sekaligus dan di-cache. Setelah commit cache ditulis ulang dari database
 * (bukan sekadar dibuang), sehingga permintaan lain yang membaca baris lama sebelum commit
 * tidak bisa mengisi cache dengan nilai basi tanpa batas; TTL terbatas menjadi jaring pengaman.
 */
final class SettingsRepository
{
    private const CACHE_KEY = 'settings.values';

    private const CACHE_TTL_MINUTES = 10;

    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        /** @var array<string, mixed> */
        return Cache::remember(
            self::CACHE_KEY,
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn (): array => $this->loadFromDatabase(),
        );
    }

    public function get(SettingKey $key, mixed $default = null): mixed
    {
        return $this->all()[$key->value] ?? $default;
    }

    /**
     * @return array{app_name: string, foundation_name: string, address: ?string, phone: ?string, email: ?string, logo_path: ?string, favicon_path: ?string}
     */
    public function identity(): array
    {
        return [
            'app_name' => $this->stringOr(SettingKey::AppName, (string) config('app.name')),
            'foundation_name' => $this->stringOr(SettingKey::FoundationName, (string) config('sekolah.foundation_name')),
            'address' => $this->stringOrNull(SettingKey::Address),
            'phone' => $this->stringOrNull(SettingKey::Phone),
            'email' => $this->stringOrNull(SettingKey::Email),
            'logo_path' => $this->stringOrNull(SettingKey::LogoPath),
            'favicon_path' => $this->stringOrNull(SettingKey::FaviconPath),
        ];
    }

    /**
     * Simpan beberapa kunci dalam satu transaksi + satu baris audit berisi kunci yang berubah.
     * Mengembalikan nilai lama kunci yang berubah, dibaca di bawah `lockForUpdate`
     * (bukan dari cache), agar pemanggil bisa membersihkan berkas lama dengan tepat.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function setMany(array $values, User $actor): array
    {
        foreach (array_keys($values) as $key) {
            if (SettingKey::tryFrom($key) === null) {
                throw new InvalidArgumentException("Kunci pengaturan [{$key}] tidak dikenal.");
            }
        }

        return DB::transaction(function () use ($values, $actor): array {
            $current = Setting::query()
                ->whereIn('key', array_keys($values))
                ->orderBy('key')
                ->lockForUpdate()
                ->get()
                ->keyBy('key');

            $before = [];
            $after = [];

            foreach ($values as $key => $value) {
                $existing = $current->get($key);
                $old = $existing?->value;

                if ($existing !== null && $old === $value) {
                    continue;
                }

                $before[$key] = $old;
                $after[$key] = $value;
            }

            if ($after === []) {
                return [];
            }

            $now = now();

            // Satu query untuk semua kunci; upsert melewati cast, jadi nilai di-encode manual.
            Setting::query()->upsert(
                array_map(fn (string $key): array => [
                    'key' => $key,
                    'value' => json_encode($after[$key], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_by' => $actor->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], array_keys($after)),
                uniqueBy: ['key'],
                update: ['value', 'updated_by', 'updated_at'],
            );

            $this->auditLogger->record(
                action: 'settings_updated',
                subject: Setting::class,
                before: $before,
                after: $after,
                userId: $actor->id,
            );

            DB::afterCommit(fn () => Cache::put(
                self::CACHE_KEY,
                $this->loadFromDatabase(),
                now()->addMinutes(self::CACHE_TTL_MINUTES),
            ));

            return $before;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFromDatabase(): array
    {
        return Setting::query()
            ->orderBy('key')
            ->get(['key', 'value'])
            ->mapWithKeys(fn (Setting $setting): array => [$setting->key => $setting->value])
            ->all();
    }

    private function stringOr(SettingKey $key, string $default): string
    {
        $value = $this->get($key);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function stringOrNull(SettingKey $key): ?string
    {
        $value = $this->get($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
