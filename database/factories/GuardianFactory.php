<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GuardianRelation;
use App\Enums\Role;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @extends Factory<Guardian>
 */
class GuardianFactory extends Factory
{
    protected $model = Guardian::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fn (array $attributes): string => User::query()->whereKey($attributes['user_id'])->firstOrFail()->name,
            // Nomor HP wali selalu sama dengan akun login-nya.
            'phone' => fn (array $attributes): string => (string) User::query()->whereKey($attributes['user_id'])->firstOrFail()->phone,
            'relation' => GuardianRelation::Father,
            'address' => null,
        ];
    }

    /**
     * Akun login ber-username nomor HP dengan peran wali, seperti yang dibuat GuardianService.
     */
    public function withGuardianAccount(): static
    {
        return $this->afterCreating(function (Guardian $guardian): void {
            $guardian->user->forceFill(['username' => $guardian->phone])->save();

            if (SpatieRole::query()->where('name', Role::Guardian->value)->exists()) {
                $guardian->user->assignRole(Role::Guardian->value);
            }
        });
    }

    public function linkedTo(Student $student, bool $primary = true): static
    {
        return $this->afterCreating(function (Guardian $guardian) use ($student, $primary): void {
            $guardian->students()->attach($student->id, [
                'relation' => $guardian->relation->value,
                'is_primary' => $primary,
            ]);
        });
    }
}
