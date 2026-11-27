<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateIdentitySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::SettingsManage->value) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'app_name' => ['required', 'string', 'max:60'],
            'foundation_name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s().]+$/'],
            'email' => ['nullable', 'email', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'app_name' => 'nama aplikasi',
            'foundation_name' => 'nama yayasan',
            'address' => 'alamat',
            'phone' => 'telepon',
        ];
    }

    /**
     * @return array{app_name: string, foundation_name: string, address: ?string, phone: ?string, email: ?string}
     */
    public function identityData(): array
    {
        /** @var array{app_name: string, foundation_name: string, address?: ?string, phone?: ?string, email?: ?string} $validated */
        $validated = $this->validated();

        return [
            'app_name' => trim($validated['app_name']),
            'foundation_name' => trim($validated['foundation_name']),
            'address' => $this->nullableTrim($validated['address'] ?? null),
            'phone' => PhoneNumber::normalize($validated['phone'] ?? null),
            'email' => $this->nullableTrim($validated['email'] ?? null),
        ];
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
