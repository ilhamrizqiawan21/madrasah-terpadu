<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Users;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::enum(Permission::class)],
        ];
    }

    /**
     * @return list<string>
     */
    public function permissionNames(): array
    {
        /** @var list<string> $names */
        $names = $this->validated('permissions', []);

        return $names;
    }
}
