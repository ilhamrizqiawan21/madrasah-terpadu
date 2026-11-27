<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DisciplineThreshold;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DisciplineThreshold>
 */
class DisciplineThresholdFactory extends Factory
{
    protected $model = DisciplineThreshold::class;

    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'points' => fake()->unique()->numberBetween(10, 100),
            'action_name' => 'Pembinaan BK',
            'notify_guardian' => true,
            'is_active' => true,
        ];
    }
}
