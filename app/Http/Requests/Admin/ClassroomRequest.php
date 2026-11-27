<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Models\Classroom;
use App\Services\Classrooms\ClassroomService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClassroomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ClassroomsManage->value) === true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['grade', 'name'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $classroom = $this->route('classroom');
        $editing = $classroom instanceof Classroom;

        // Saat mengubah, unit dan tahun ajaran mengikuti rombel yang ada (tidak bisa dipindah).
        $unitId = $editing ? $classroom->unit_id : $this->integer('unit_id');
        $yearId = $editing ? $classroom->academic_year_id : $this->integer('academic_year_id');

        $rules = [
            'grade' => ['required', 'string', 'max:10'],
            'name' => [
                'required', 'string', 'max:30',
                Rule::unique('classrooms', 'name')
                    ->where('unit_id', $unitId)
                    ->where('academic_year_id', $yearId)
                    ->ignore($editing ? $classroom->id : null),
            ],
            'homeroom_teacher_id' => [
                'nullable', 'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! ClassroomService::homeroomCandidates()->whereKey((int) $value)->exists()) {
                        $fail('Wali kelas harus pengguna aktif dengan peran guru.');
                    }
                },
            ],
        ];

        if (! $editing) {
            $rules['unit_id'] = ['required', 'integer', Rule::exists('units', 'id')];
            $rules['academic_year_id'] = ['required', 'integer', Rule::exists('academic_years', 'id')];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'unit_id' => 'unit',
            'academic_year_id' => 'tahun ajaran',
            'grade' => 'tingkat',
            'name' => 'nama rombel',
            'homeroom_teacher_id' => 'wali kelas',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Nama rombel ini sudah dipakai di unit dan tahun ajaran yang sama.',
        ];
    }

    /**
     * @return array{unit_id: int, academic_year_id: int, grade: string, name: string, homeroom_teacher_id: int|null}
     */
    public function classroomData(): array
    {
        $teacherId = $this->validated('homeroom_teacher_id');

        return [
            'unit_id' => (int) $this->validated('unit_id'),
            'academic_year_id' => (int) $this->validated('academic_year_id'),
            'grade' => (string) $this->validated('grade'),
            'name' => (string) $this->validated('name'),
            'homeroom_teacher_id' => $teacherId === null ? null : (int) $teacherId,
        ];
    }
}
