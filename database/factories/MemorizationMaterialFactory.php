<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MemorizationMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemorizationMaterial>
 */
class MemorizationMaterialFactory extends Factory
{
    protected $model = MemorizationMaterial::class;

    public function definition(): array
    {
        return [
            'unit_id' => null,
            'kind' => 'hadith',
            'title' => fake()->sentence(3),
            'arabic_text' => 'إِنَّمَا الأَعْمَالُ بِالنِّيَّاتِ',
            'translation' => fake()->sentence(6),
            'source' => 'HR. Bukhari',
            'sort_order' => 1,
            'is_active' => true,
        ];
    }
}
