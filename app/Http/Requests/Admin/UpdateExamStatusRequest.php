<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\ExamStatus;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExamStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user('web');
        /** @var Exam|null $exam */
        $exam = $this->route('exam');

        return $user instanceof User && $exam instanceof Exam && $user->can('update', $exam);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(array_map(fn (ExamStatus $s): string => $s->value, ExamStatus::cases()))],
        ];
    }
}
