<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CopyClassroomsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ClassroomsManage->value) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')],
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')],
            'source_academic_year_id' => [
                'required', 'integer', Rule::exists('academic_years', 'id'), 'different:academic_year_id',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'unit_id' => 'unit',
            'academic_year_id' => 'tahun ajaran tujuan',
            'source_academic_year_id' => 'tahun ajaran sumber',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_academic_year_id.different' => 'Pilih tahun ajaran sumber yang berbeda dari tahun ajaran tujuan.',
        ];
    }
}
