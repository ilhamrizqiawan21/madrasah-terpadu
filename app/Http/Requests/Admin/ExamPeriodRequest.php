<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\ExamDelivery;
use App\Enums\ExamRequirementScope;
use App\Models\ExamPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExamPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isUpdate = $this->route('examPeriod') instanceof ExamPeriod;

        return [
            'unit_id' => $isUpdate
                ? ['sometimes', 'integer', 'exists:units,id']
                : ['required', 'integer', 'exists:units,id'],
            'name' => ['required', 'string', 'max:150'],
            'delivery' => ['required', Rule::enum(ExamDelivery::class)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'requirements' => ['required', 'array', 'min:1'],
            'requirements.*.fee_type_id' => ['required', 'integer', 'exists:fee_types,id'],
            'requirements.*.invoice_scope' => ['required', Rule::enum(ExamRequirementScope::class)],
            'requirements.*.period_until' => ['nullable', 'date'],
        ];
    }
}
