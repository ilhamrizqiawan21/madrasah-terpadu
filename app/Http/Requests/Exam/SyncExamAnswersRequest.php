<?php

declare(strict_types=1);

namespace App\Http\Requests\Exam;

use Illuminate\Foundation\Http\FormRequest;

class SyncExamAnswersRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'device_token' => ['required', 'string', 'max:64'],
            'answers' => ['required', 'array', 'max:100'],
            'answers.*.exam_question_id' => ['required', 'integer'],
            'answers.*.answer' => ['nullable', 'array'],
            'answers.*.is_flagged' => ['required', 'boolean'],
            'answers.*.client_ts' => ['required', 'date'],
        ];
    }
}
