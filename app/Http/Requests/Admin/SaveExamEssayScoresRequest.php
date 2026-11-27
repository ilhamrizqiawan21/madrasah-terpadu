<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Exam;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveExamEssayScoresRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user('web');
        /** @var Exam|null $exam */
        $exam = $this->route('exam');

        if (! $user instanceof User || ! $exam instanceof Exam) {
            return false;
        }

        if ($user->cannot('update', $exam)) {
            abort(404);
        }

        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Exam $exam */
        $exam = $this->route('exam');
        $maxWeight = max(0, (int) ($exam->weights['essay_paper'] ?? 4));

        return [
            'exam_session_id' => [
                'required',
                'integer',
                Rule::exists('exam_sessions', 'id')->where('exam_id', $exam->id),
            ],
            'scores' => ['required', 'array', 'min:1'],
            'scores.*.exam_question_id' => ['required', 'integer'],
            'scores.*.score' => ['required', 'integer', 'min:0', "max:{$maxWeight}"],
        ];
    }
}
