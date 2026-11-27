<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\Student;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'nis' => fake()->unique()->numerify('########'),
            'nisn' => null,
            'name' => 'Siswa Uji '.fake()->unique()->numerify('####'),
            'nickname' => null,
            'gender' => fake()->randomElement(Gender::cases()),
            'birth_place' => null,
            'birth_date' => null,
            'address' => null,
            'photo_path' => null,
            'status' => StudentStatus::Active,
        ];
    }

    public function status(StudentStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
