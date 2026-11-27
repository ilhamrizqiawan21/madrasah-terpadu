<?php

declare(strict_types=1);

namespace App\Http\Requests\Exam;

use App\Enums\ExamViolationKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordExamViolationRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'device_token' => ['required', 'string', 'max:64'],
            'kind' => ['required', 'string', Rule::in(array_map(fn (ExamViolationKind $k): string => $k->value, ExamViolationKind::cases()))],
        ];
    }
}
