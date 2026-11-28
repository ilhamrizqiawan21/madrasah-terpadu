<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\EmploymentStatus;
use App\Enums\StaffAssignmentType;
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

        foreach (['title_prefix', 'title_suffix', 'nik', 'nuptk', 'gender', 'birth_place', 'birth_date', 'address', 'password'] as $field) {
            $value = $this->input($field);
            $this->merge([$field => is_string($value) && trim($value) !== '' ? trim($value) : null]);
        }

        foreach (['base_salary', 'allowance', 'hourly_rate'] as $field) {
            $this->merge([$field => $this->filled($field) ? $this->input($field) : 0]);
        }
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
            'title_prefix' => ['nullable', 'string', 'max:20'],
            'title_suffix' => ['nullable', 'string', 'max:40'],
            'nik' => ['nullable', 'digits:16'],
            'nuptk' => ['nullable', 'digits:16'],
            'gender' => ['nullable', Rule::in(['L', 'P'])],
            'birth_place' => ['nullable', 'string', 'max:60'],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'address' => ['nullable', 'string', 'max:255'],
            'assignment_type' => ['nullable', Rule::enum(StaffAssignmentType::class)],
            'employment_status' => ['nullable', Rule::enum(EmploymentStatus::class)],
            'base_salary' => ['integer', 'min:0', 'max:1000000000'],
            'allowance' => ['integer', 'min:0', 'max:1000000000'],
            'hourly_rate' => ['integer', 'min:0', 'max:100000000'],
            'password' => ['nullable', 'string', 'min:8', 'max:100'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
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
            'title_prefix' => 'gelar depan',
            'title_suffix' => 'gelar belakang',
            'nik' => 'NIK',
            'nuptk' => 'NUPTK',
            'gender' => 'jenis kelamin',
            'birth_place' => 'tempat lahir',
            'birth_date' => 'tanggal lahir',
            'address' => 'alamat',
            'assignment_type' => 'tipe penugasan',
            'employment_status' => 'status ikatan kerja',
            'base_salary' => 'gaji pokok',
            'allowance' => 'tunjangan',
            'hourly_rate' => 'tarif honor per JP',
            'password' => 'kata sandi',
            'photo' => 'pas foto',
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

    /**
     * Biodata, status kerja, dan penggajian pegawai (staff_profiles).
     *
     * @return array<string, mixed>
     */
    public function profileData(): array
    {
        return $this->safe()->only([
            'title_prefix', 'title_suffix', 'nik', 'nuptk', 'gender', 'birth_place', 'birth_date', 'address',
            'assignment_type', 'employment_status', 'base_salary', 'allowance', 'hourly_rate',
        ]);
    }

    public function chosenPassword(): ?string
    {
        $password = $this->validated('password');

        return is_string($password) ? $password : null;
    }
}
