<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ExamQuestionType;
use App\Models\Exam;
use App\Models\ExamQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamQuestion>
 */
class ExamQuestionFactory extends Factory
{
    protected $model = ExamQuestion::class;

    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'type' => ExamQuestionType::Mcq,
            'body' => fake()->sentence(8),
            'is_rtl' => false,
            'image_path' => null,
            'sort_order' => 1,
            'answer_key' => ['correct' => 'A'],
        ];
    }
}
