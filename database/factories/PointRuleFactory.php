<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PointCategory;
use App\Models\PointRule;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PointRule>
 */
class PointRuleFactory extends Factory
{
    protected $model = PointRule::class;

    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'code' => mb_strtoupper(fake()->unique()->bothify('PR-###')),
            'name' => fake()->sentence(3),
            'category' => PointCategory::Violation,
            'level' => null,
            'points' => fake()->numberBetween(2, 20),
            'is_active' => true,
        ];
    }
}
