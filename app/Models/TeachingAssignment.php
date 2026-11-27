<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Permission;
use App\Enums\Role;
use Database\Factories\TeachingAssignmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penugasan guru × mapel × rombel × tahun ajaran (T-1501).
 *
 * @property int $id
 * @property int $user_id
 * @property int $subject_id
 * @property int $classroom_id
 * @property int $academic_year_id
 */
class TeachingAssignment extends Model
{
    /** @use HasFactory<TeachingAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subject_id',
        'classroom_id',
        'academic_year_id',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * Guru tanpa izin kelola hanya melihat penugasan miliknya sendiri.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can(Permission::CbtManage->value) || $user->hasRole(Role::UnitHead->value)) {
            return $query;
        }

        return $query->where('user_id', $user->id);
    }
}
