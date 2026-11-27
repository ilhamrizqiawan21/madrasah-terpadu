<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FeeRecurrence;
use App\Models\FeeType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeType>
 */
class FeeTypeFactory extends Factory
{
    protected $model = FeeType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => null,
            'name' => fake()->unique()->numerify('SPP ###'),
            'category' => 'SPP',
            'recurrence' => FeeRecurrence::Monthly,
            'default_amount' => 150000,
            'is_active' => true,
        ];
    }
}
