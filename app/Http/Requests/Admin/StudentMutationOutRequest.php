<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Enums\StudentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentMutationOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('web')?->can(Permission::StudentsManage->value) === true;
    }

    protected function prepareForValidation(): void
    {
        $trimmed = [];

        foreach (['school_name', 'reason'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $value = trim($value);
                $trimmed[$field] = $value === '' && $field === 'school_name' ? null : $value;
            }
        }

        $this->merge($trimmed);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', Rule::exists('students', 'id')->where('status', StudentStatus::Active->value)],
            'mutated_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'school_name' => ['nullable', 'string', 'max:120'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'student_id' => 'siswa',
            'mutated_on' => 'tanggal keluar',
            'school_name' => 'sekolah tujuan',
            'reason' => 'alasan mutasi',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'student_id.exists' => 'Pilih siswa aktif yang valid.',
            'mutated_on.before_or_equal' => 'Tanggal mutasi tidak boleh setelah hari ini.',
        ];
    }
}
