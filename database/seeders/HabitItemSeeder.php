<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\HabitAspect;
use App\Models\HabitItem;
use Illuminate\Database\Seeder;

class HabitItemSeeder extends Seeder
{
    public function run(): void
    {
        $prayers = [
            'Sholat Subuh',
            'Sholat Dzuhur',
            'Sholat Ashar',
            'Sholat Maghrib',
            'Sholat Isya',
        ];

        foreach ($prayers as $index => $name) {
            HabitItem::query()->updateOrCreate(
                ['unit_id' => null, 'name' => $name, 'is_locked' => true],
                [
                    'aspect' => HabitAspect::Spiritual->value,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
