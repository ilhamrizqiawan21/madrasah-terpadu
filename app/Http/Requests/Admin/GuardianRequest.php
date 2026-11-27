<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\GuardianRelation;
use App\Enums\Permission;
use App\Models\Guardian;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardianRequest extends FormRequest
{
    public const MAX_CHILDREN = 10;

    public const PHONE_MESSAGE = 'Nomor HP harus nomor seluler Indonesia, mis. 0812xxxxxxxx.';

    public function authorize(): bool
    {
        return $this->user('web')?->can(Permission::GuardiansManage->value) === true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['name', 'address'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $value = trim($value);
                $merge[$field] = $value === '' && $field === 'address' ? null : $value;
            }
        }

        $phone = $this->input('phone');

        if (is_string($phone)) {
            // Username wali = nomor HP ternormalisasi, sehingga "+62 812-..." dan "0812..." dianggap sama.
            $merge['phone'] = PhoneNumber::normalize($phone) ?? '';
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $guardian = $this->editedGuardian();

        $rules = self::contactRules();
        $phone = $rules['phone'];

        // Saat menambah, nomor yang sudah dipakai akun lain tidak ditolak: GuardianService menautkannya.
        if ($guardian !== null) {
            $phone[] = Rule::unique('users', 'phone')->ignore($guardian->user_id);
            $phone[] = Rule::unique('users', 'username')->ignore($guardian->user_id);
            $phone[] = Rule::unique('guardians', 'phone')->ignore($guardian->id);
        }

        return [
            ...$rules,
            'phone' => $phone,
            'address' => ['nullable', 'string', 'max:500'],
            'children' => ['required', 'array', 'min:1', 'max:'.self::MAX_CHILDREN],
            'children.*' => ['required', 'array:student_id,is_primary'],
            'children.*.student_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('students', 'id')->whereNull('deleted_at'),
            ],
            'children.*.is_primary' => ['required', 'boolean'],
        ];
    }

    /**
     * Aturan nama, nomor HP (sudah dinormalisasi), dan hubungan wali; dipakai juga oleh impor Excel (T-207).
     *
     * @return array<string, list<mixed>>
     */
    public static function contactRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^08[0-9]{8,12}$/'],
            'relation' => ['required', Rule::enum(GuardianRelation::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama wali',
            'phone' => 'nomor HP',
            'relation' => 'hubungan',
            'address' => 'alamat',
            'children' => 'anak',
            'children.*.student_id' => 'anak',
            'children.*.is_primary' => 'wali utama',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => self::PHONE_MESSAGE,
            'phone.unique' => 'Nomor HP ini sudah dipakai akun lain.',
            'children.required' => 'Pilih minimal satu anak.',
            'children.min' => 'Pilih minimal satu anak.',
            'children.max' => 'Satu wali paling banyak terhubung dengan '.self::MAX_CHILDREN.' anak.',
            'children.*.student_id.distinct' => 'Anak yang sama dipilih lebih dari sekali.',
            'children.*.student_id.exists' => 'Siswa yang dipilih tidak ditemukan.',
        ];
    }

    /**
     * @return array{name: string, phone: string, relation: GuardianRelation, address: string|null, children: list<array{student_id: int, is_primary: bool}>}
     */
    public function guardianData(): array
    {
        /** @var array<int, array{student_id: int|string, is_primary: bool|int|string}> $children */
        $children = $this->validated('children');
        $address = $this->validated('address');

        return [
            'name' => (string) $this->validated('name'),
            'phone' => (string) $this->validated('phone'),
            'relation' => GuardianRelation::from((string) $this->validated('relation')),
            'address' => $address === null ? null : (string) $address,
            'children' => array_values(array_map(fn (array $child): array => [
                'student_id' => (int) $child['student_id'],
                'is_primary' => filter_var($child['is_primary'], FILTER_VALIDATE_BOOLEAN),
            ], $children)),
        ];
    }

    private function editedGuardian(): ?Guardian
    {
        $guardian = $this->route('guardian');

        return $guardian instanceof Guardian ? $guardian : null;
    }
}
