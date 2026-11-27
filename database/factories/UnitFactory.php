<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Unit;
use App\Support\AttendanceHours;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = 'U'.fake()->unique()->numerify('###');

        return [
            'code' => $code,
            'name' => "Unit Uji {$code}",
            'head_user_id' => null,
            'attendance_settings' => AttendanceHours::defaults(),
            'is_active' => true,
        ];
    }
}
