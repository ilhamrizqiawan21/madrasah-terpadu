<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Services\Students\StudentPromotionService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentPromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::StudentsManage->value) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('is_active', true)],
            'source_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'source_classroom_id' => ['required', 'integer', 'exists:classrooms,id'],
            'target_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'mode' => ['required', 'string', Rule::in([StudentPromotionService::MODE_PROMOTE, StudentPromotionService::MODE_GRADUATE])],
            'target_classroom_id' => [
                Rule::requiredIf(fn (): bool => $this->input('mode') === StudentPromotionService::MODE_PROMOTE),
                'nullable',
                'integer',
                'exists:classrooms,id',
            ],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['required', 'integer', 'exists:students,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'unit_id' => 'unit',
            'source_year_id' => 'tahun ajaran asal',
            'source_classroom_id' => 'rombel asal',
            'target_year_id' => 'tahun ajaran tujuan',
            'target_classroom_id' => 'rombel tujuan',
            'student_ids' => 'daftar siswa',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'student_ids.required' => 'Pilih minimal satu siswa untuk dinaikkan atau diluluskan.',
            'student_ids.min' => 'Pilih minimal satu siswa untuk dinaikkan atau diluluskan.',
            'target_classroom_id.required' => 'Pilih rombel tujuan.',
        ];
    }
}
