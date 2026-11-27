<?php

declare(strict_types=1);

namespace App\Http\Requests\Exam;

use Illuminate\Foundation\Http\FormRequest;

class ExamLoginRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('nis') && is_string($this->input('nis'))) {
            $this->merge(['nis' => trim($this->input('nis'))]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'nis' => ['required', 'string', 'max:20'],
            'pin' => ['required', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nis' => 'NIS',
            'pin' => 'PIN ujian',
        ];
    }
}
