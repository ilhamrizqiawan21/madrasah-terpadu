<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\HabitItem;
use App\Models\User;

class HabitItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::HabitsManage->value)
            || $user->can(Permission::HabitsVerify->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::HabitsManage->value);
    }

    public function update(User $user, HabitItem $habitItem): bool
    {
        return $user->can(Permission::HabitsManage->value) && ! $habitItem->is_locked;
    }

    public function toggle(User $user, HabitItem $habitItem): bool
    {
        return $user->can(Permission::HabitsManage->value) && ! $habitItem->is_locked;
    }

    public function delete(User $user, HabitItem $habitItem): bool
    {
        return $user->can(Permission::HabitsManage->value) && ! $habitItem->is_locked;
    }

    public function reorder(User $user, HabitItem $habitItem): bool
    {
        return $user->can(Permission::HabitsManage->value);
    }
}
