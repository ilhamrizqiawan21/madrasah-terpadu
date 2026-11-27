<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\NotificationCategory;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Pengelolaan preferensi notifikasi Web Push per kategori (T-403).
 * Kategori terkunci (`billing`, `account`) selalu aktif; tidak ada baris = aktif.
 */
final class NotificationPreferenceService
{
    /**
     * @return list<array{category: string, label: string, enabled: bool, locked: bool}>
     */
    public function forUser(User $user): array
    {
        $stored = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->pluck('enabled', 'category')
            ->all();

        $items = [];

        foreach (NotificationCategory::cases() as $category) {
            $locked = $category->isLocked();
            $enabled = $locked ? true : (bool) ($stored[$category->value] ?? true);

            $items[] = [
                'category' => $category->value,
                'label' => $category->label(),
                'enabled' => $enabled,
                'locked' => $locked,
            ];
        }

        return $items;
    }

    public function isPushEnabled(User $user, NotificationCategory $category): bool
    {
        if ($category->isLocked()) {
            return true;
        }

        $pref = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('category', $category->value)
            ->first();

        return $pref === null ? true : $pref->enabled;
    }

    /**
     * @param  array<string, bool>  $preferences
     */
    public function update(User $user, array $preferences): void
    {
        DB::transaction(function () use ($user, $preferences): void {
            foreach ($preferences as $key => $enabled) {
                $category = NotificationCategory::tryFrom($key);

                if ($category === null || $category->isLocked()) {
                    continue;
                }

                NotificationPreference::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'category' => $category->value,
                    ],
                    [
                        'enabled' => $enabled,
                    ],
                );
            }
        });
    }
}
