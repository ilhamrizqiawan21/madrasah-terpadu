<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Services\Users\StaffUserService;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Tambah dan ubah akun staf. Otorisasi lewat UserPolicy di controller.
 */
class StaffUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');
        $email = $this->input('email');
        $username = $this->input('username');

        $this->merge([
            'phone' => is_string($phone) ? PhoneNumber::normalize($phone) : null,
            'email' => is_string($email) && trim($email) !== '' ? Str::lower(trim($email)) : null,
            'username' => is_string($username) ? Str::lower(trim($username)) : $username,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $target = $this->route('staffUser');
        $ignoreId = $target instanceof User ? $target->id : null;

        return [
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique(User::class, 'username')->ignore($ignoreId)],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique(User::class, 'phone')->ignore($ignoreId)],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($ignoreId)],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', 'distinct', Rule::in(StaffUserService::staffRoleValues())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'username' => 'Username',
            'roles' => 'peran',
            'roles.*' => 'peran',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => 'Username hanya boleh berisi huruf kecil, angka, titik, tanda hubung, dan garis bawah.',
        ];
    }

    /**
     * @return array{name: string, username: string, phone: ?string, email: ?string, roles: list<string>}
     */
    public function staffData(): array
    {
        /** @var array{name: string, username: string, phone?: ?string, email?: ?string, roles: array<int|string, string>} $validated */
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'username' => $validated['username'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'roles' => array_values($validated['roles']),
        ];
    }
}
