<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use Carbon\CarbonImmutable;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Siswa. Ditulis hanya lewat App\Services\Students\StudentService.
 * Siswa tidak dihapus dari UI; statusnya diubah (nonaktif, lulus, pindah).
 *
 * @property int $id
 * @property int $unit_id
 * @property string $nis
 * @property string|null $nisn
 * @property string $name
 * @property string|null $nickname
 * @property Gender $gender
 * @property string|null $birth_place
 * @property CarbonImmutable|null $birth_date
 * @property string|null $address
 * @property string|null $photo_path
 * @property StudentStatus $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Unit $unit
 * @property-read Collection<int, Enrollment> $enrollments
 * @property-read Collection<int, Guardian> $guardians
 * @property-read Collection<int, Guardian> $primaryGuardians
 */
#[Fillable([
    'unit_id', 'nis', 'nisn', 'name', 'nickname', 'gender',
    'birth_place', 'birth_date', 'address', 'photo_path', 'status',
])]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'status' => StudentStatus::class,
            'birth_date' => 'immutable_date',
        ];
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * @return BelongsToMany<Guardian, $this, GuardianStudent, 'link'>
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class)
            ->using(GuardianStudent::class)
            ->as('link')
            ->withPivot(['id', 'relation', 'is_primary']);
    }

    /**
     * Wali utama (paling banyak satu). Dipakai kolom Wali di daftar siswa dan login NIS.
     *
     * @return BelongsToMany<Guardian, $this, GuardianStudent, 'link'>
     */
    public function primaryGuardians(): BelongsToMany
    {
        return $this->guardians()->wherePivot('is_primary', true);
    }
}
