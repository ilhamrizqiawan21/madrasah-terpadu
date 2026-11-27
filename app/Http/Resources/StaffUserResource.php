<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class StaffUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $roles = [];

        foreach ($this->getRoleNames() as $roleName) {
            $enum = Role::tryFrom($roleName);

            if ($enum !== null && $enum !== Role::Guardian) {
                $roles[] = ['value' => $enum->value, 'label' => $enum->label()];
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'phone' => $this->phone,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'roles' => $roles,
            'two_factor_enabled' => $this->two_factor_confirmed_at !== null,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'is_self' => $request->user()?->id === $this->id,
        ];
    }
}
