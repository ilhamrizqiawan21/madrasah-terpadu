<?php

declare(strict_types=1);

namespace App\Http\Requests\Exam;

use App\Enums\ExamDeviceMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartExamSessionRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'device_type' => ['required', 'string', Rule::in(array_map(fn (ExamDeviceMode $m): string => $m->value, ExamDeviceMode::cases()))],
            'access_token' => ['nullable', 'string', 'max:20'],
        ];
    }
}
