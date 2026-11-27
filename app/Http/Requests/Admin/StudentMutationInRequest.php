<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Models\AcademicYear;
use App\Models\Classroom;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentMutationInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('web')?->can(Permission::StudentsManage->value) === true;
    }

    protected function prepareForValidation(): void
    {
        $trimmed = [];

        foreach (['nis', 'nisn', 'name', 'nickname', 'birth_place', 'address', 'school_name', 'reason'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $value = trim($value);
                $trimmed[$field] = $value === '' && ! in_array($field, ['nis', 'name', 'reason'], true) ? null : $value;
            }
        }

        $this->merge($trimmed);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $unitId = $this->integer('unit_id');
        $rules = StudentRequest::fieldRules();
        $rules['nis'][] = Rule::unique('students', 'nis');
        $rules['nisn'][] = Rule::unique('students', 'nisn');

        return [
            ...$rules,
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('is_active', true)],
            'classroom_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, Closure $fail) use ($unitId): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $activeYear = AcademicYear::query()->where('is_active', true)->first();
                    $classroom = Classroom::query()->find((int) $value);

                    if (
                        $activeYear === null
                        || $classroom === null
                        || ! $classroom->is_active
                        || $classroom->unit_id !== $unitId
                        || $classroom->academic_year_id !== $activeYear->id
                    ) {
                        $fail('Rombel harus aktif pada unit siswa dan tahun ajaran aktif.');
                    }
                },
            ],
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
            ...StudentRequest::fieldAttributes(),
            'unit_id' => 'unit',
            'classroom_id' => 'rombel',
            'mutated_on' => 'tanggal masuk',
            'school_name' => 'asal sekolah',
            'reason' => 'alasan mutasi',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...StudentRequest::fieldMessages(),
            'nis.unique' => 'NIS ini sudah dipakai siswa lain.',
            'mutated_on.before_or_equal' => 'Tanggal mutasi tidak boleh setelah hari ini.',
        ];
    }
}
