<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RewardOffer;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RewardOffer>
 */
class RewardOfferFactory extends Factory
{
    protected $model = RewardOffer::class;

    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'name' => 'Voucher Kantin '.fake()->numberBetween(5, 25).'rb',
            'cost_points' => 25,
            'voucher_amount' => 10000,
            'voucher_valid_days' => 14,
            'stock' => 10,
            'is_active' => true,
        ];
    }
}
