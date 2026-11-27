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
        $nip = $this->input('nip');
        $position = $this->input('position');

        $this->merge([
            'phone' => is_string($phone) ? PhoneNumber::normalize($phone) : null,
            'email' => is_string($email) && trim($email) !== '' ? Str::lower(trim($email)) : null,
            'username' => is_string($username) ? Str::lower(trim($username)) : $username,
            'nip' => is_string($nip) && trim($nip) !== '' ? trim($nip) : null,
            'position' => is_string($position) && trim($position) !== '' ? trim($position) : null,
            'unit_id' => $this->filled('unit_id') ? $this->integer('unit_id') : null,
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
            'unit_id' => ['nullable', 'integer', Rule::exists('units', 'id')->where('is_active', true)],
            'nip' => ['nullable', 'string', 'max:30'],
            'position' => ['nullable', 'string', 'max:80'],
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
            'unit_id' => 'unit',
            'nip' => 'NIP',
            'position' => 'jabatan',
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
     * @return array{name: string, username: string, phone: ?string, email: ?string, roles: list<string>, unit_id: ?int, nip: ?string, position: ?string}
     */
    public function staffData(): array
    {
        /** @var array{name: string, username: string, phone?: ?string, email?: ?string, roles: array<int|string, string>, unit_id?: ?int, nip?: ?string, position?: ?string} $validated */
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'username' => $validated['username'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'roles' => array_values($validated['roles']),
            'unit_id' => $validated['unit_id'] ?? null,
            'nip' => $validated['nip'] ?? null,
            'position' => $validated['position'] ?? null,
        ];
    }
}
