<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmploymentStatus;
use App\Enums\StaffAssignmentType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Profil kepegawaian staf: unit, NIP, dan jabatan (T-209).
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $unit_id
 * @property string|null $nip
 * @property string|null $position
 * @property string|null $title_prefix
 * @property string|null $title_suffix
 * @property string|null $nik
 * @property string|null $nuptk
 * @property string|null $gender
 * @property string|null $birth_place
 * @property CarbonImmutable|null $birth_date
 * @property string|null $address
 * @property StaffAssignmentType|null $assignment_type
 * @property EmploymentStatus|null $employment_status
 * @property int $base_salary
 * @property int $allowance
 * @property int $hourly_rate
 * @property string|null $photo_path
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read Unit|null $unit
 */
#[Fillable([
    'user_id', 'unit_id', 'nip', 'position',
    'title_prefix', 'title_suffix', 'nik', 'nuptk', 'gender', 'birth_place', 'birth_date', 'address',
    'assignment_type', 'employment_status', 'base_salary', 'allowance', 'hourly_rate', 'photo_path',
])]
class StaffProfile extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'immutable_date',
            'assignment_type' => StaffAssignmentType::class,
            'employment_status' => EmploymentStatus::class,
            'base_salary' => 'integer',
            'allowance' => 'integer',
            'hourly_rate' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
