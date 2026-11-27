<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Subject;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'name' => fake()->words(2, true),
            'is_active' => true,
        ];
    }
}
