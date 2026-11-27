<?php

declare(strict_types=1);

namespace App\Services\Habits;

use App\Enums\HabitAspect;
use App\Models\HabitItem;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class HabitItemService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): HabitItem
    {
        $unitId = isset($data['unit_id']) && $data['unit_id'] !== '' ? (int) $data['unit_id'] : null;
        $aspect = HabitAspect::from((string) ($data['aspect'] ?? ''));

        return DB::transaction(function () use ($actor, $unitId, $aspect, $data): HabitItem {
            $maxOrder = (int) HabitItem::query()->max('sort_order');

            $item = HabitItem::query()->create([
                'unit_id' => $unitId,
                'name' => trim((string) ($data['name'] ?? '')),
                'aspect' => $aspect->value,
                'sort_order' => $maxOrder + 1,
                'is_active' => true,
                'is_locked' => false,
            ]);

            $this->audit->record(
                action: 'habit_item.created',
                subject: $item,
                after: $item->only(['unit_id', 'name', 'aspect', 'sort_order', 'is_active']),
                userId: $actor->id,
            );

            return $item;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, HabitItem $item, array $data): HabitItem
    {
        if ($item->is_locked) {
            throw new AuthorizationException('Item wajib sekolah tidak dapat diubah.');
        }

        $unitId = isset($data['unit_id']) && $data['unit_id'] !== '' ? (int) $data['unit_id'] : null;
        $aspect = HabitAspect::from((string) ($data['aspect'] ?? ''));

        return DB::transaction(function () use ($actor, $item, $unitId, $aspect, $data): HabitItem {
            $before = $item->only(['unit_id', 'name', 'aspect']);
            $item->update([
                'unit_id' => $unitId,
                'name' => trim((string) ($data['name'] ?? '')),
                'aspect' => $aspect->value,
            ]);

            $this->audit->record(
                action: 'habit_item.updated',
                subject: $item,
                before: $before,
                after: $item->only(['unit_id', 'name', 'aspect']),
                userId: $actor->id,
            );

            return $item;
        });
    }

    public function toggleActive(User $actor, HabitItem $item, bool $isActive): HabitItem
    {
        if ($item->is_locked) {
            throw new AuthorizationException('Item wajib sekolah tidak dapat dinonaktifkan.');
        }

        return DB::transaction(function () use ($actor, $item, $isActive): HabitItem {
            $before = ['is_active' => $item->is_active];
            $item->update(['is_active' => $isActive]);

            $this->audit->record(
                action: $isActive ? 'habit_item.activated' : 'habit_item.deactivated',
                subject: $item,
                before: $before,
                after: ['is_active' => $item->is_active],
                userId: $actor->id,
            );

            return $item;
        });
    }

    public function move(User $actor, HabitItem $item, string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw ValidationException::withMessages(['direction' => 'Arah urutan tidak valid.']);
        }

        DB::transaction(function () use ($actor, $item, $direction): void {
            $ordered = HabitItem::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->values();

            $index = $ordered->search(fn (HabitItem $row): bool => $row->id === $item->id);
            if ($index === false) {
                return;
            }

            $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;
            if (! isset($ordered[$targetIndex])) {
                return;
            }

            /** @var HabitItem $swap */
            $swap = $ordered[$targetIndex];
            $items = $ordered->all();
            $items[$index] = $swap;
            $items[$targetIndex] = $ordered[$index];

            foreach ($items as $pos => $row) {
                $newOrder = $pos + 1;
                if ($row->sort_order !== $newOrder) {
                    $row->update(['sort_order' => $newOrder]);
                }
            }

            $this->audit->record(
                action: 'habit_item.reordered',
                subject: $item,
                after: ['direction' => $direction],
                userId: $actor->id,
            );
        });
    }

    public function delete(User $actor, HabitItem $item): bool
    {
        if ($item->is_locked) {
            throw new AuthorizationException('Item wajib sekolah tidak dapat dihapus.');
        }

        return DB::transaction(function () use ($actor, $item): bool {
            $hasLogs = Schema::hasTable('habit_logs')
                && DB::table('habit_logs')->where('habit_item_id', $item->id)->exists();

            if ($hasLogs) {
                $item->update(['is_active' => false]);
                $this->audit->record(
                    action: 'habit_item.deactivated',
                    subject: $item,
                    after: ['reason' => 'has_logs'],
                    userId: $actor->id,
                );

                return false;
            }

            $this->audit->record(
                action: 'habit_item.deleted',
                subject: $item,
                before: $item->only(['name', 'aspect', 'unit_id']),
                userId: $actor->id,
            );
            $item->delete();

            return true;
        });
    }
}
