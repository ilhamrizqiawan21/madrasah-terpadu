<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\HabitAspect;
use App\Models\HabitItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HabitItem>
 */
class HabitItemFactory extends Factory
{
    protected $model = HabitItem::class;

    public function definition(): array
    {
        return [
            'unit_id' => null,
            'name' => fake()->words(3, true),
            'aspect' => HabitAspect::Spiritual,
            'sort_order' => 10,
            'is_active' => true,
            'is_locked' => false,
        ];
    }
}
