<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ExamDelivery;
use App\Enums\ExamPeriodStatus;
use App\Models\ExamPeriod;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamPeriod>
 */
class ExamPeriodFactory extends Factory
{
    protected $model = ExamPeriod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'name' => 'PAS Ganjil '.fake()->numberBetween(2025, 2027),
            'delivery' => ExamDelivery::Cbt,
            'starts_on' => now()->addDays(7)->toDateString(),
            'ends_on' => now()->addDays(14)->toDateString(),
            'status' => ExamPeriodStatus::Draft,
            'created_by' => User::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => ExamPeriodStatus::Active]);
    }
}
