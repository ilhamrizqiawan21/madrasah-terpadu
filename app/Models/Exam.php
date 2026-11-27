<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExamStatus;
use App\Enums\Permission;
use App\Enums\Role;
use Carbon\CarbonImmutable;
use Database\Factories\ExamFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ujian CBT (T-1503, ADR-020).
 *
 * @property int $id
 * @property int|null $exam_period_id
 * @property int $subject_id
 * @property string $title
 * @property string|null $grade
 * @property list<int> $classroom_ids
 * @property CarbonImmutable $opens_at
 * @property CarbonImmutable $closes_at
 * @property int $duration_minutes
 * @property bool $shuffle_questions
 * @property bool $shuffle_options
 * @property array{mcq: int, matching: int, essay_paper: int} $weights
 * @property string|null $access_token
 * @property int $violation_limit
 * @property list<string> $device_modes
 * @property bool $show_result_to_guardian
 * @property ExamStatus $status
 * @property CarbonImmutable|null $locked_at
 * @property int $created_by
 */
class Exam extends Model
{
    /** @use HasFactory<ExamFactory> */
    use HasFactory;

    protected $fillable = [
        'exam_period_id',
        'subject_id',
        'title',
        'grade',
        'classroom_ids',
        'opens_at',
        'closes_at',
        'duration_minutes',
        'shuffle_questions',
        'shuffle_options',
        'weights',
        'access_token',
        'violation_limit',
        'device_modes',
        'show_result_to_guardian',
        'status',
        'locked_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'classroom_ids' => 'array',
            'opens_at' => 'immutable_datetime',
            'closes_at' => 'immutable_datetime',
            'duration_minutes' => 'integer',
            'shuffle_questions' => 'boolean',
            'shuffle_options' => 'boolean',
            'weights' => 'array',
            'violation_limit' => 'integer',
            'device_modes' => 'array',
            'show_result_to_guardian' => 'boolean',
            'status' => ExamStatus::class,
            'locked_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ExamQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(ExamQuestion::class)->orderBy('sort_order')->orderBy('id');
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null
            || $this->status === ExamStatus::Running
            || $this->status === ExamStatus::Finished;
    }

    /**
     * Guru tanpa izin kelola hanya melihat ujian yang dibuatnya atau untuk mapel yang ditugaskan kepadanya.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can(Permission::CbtManage->value) || $user->hasRole(Role::UnitHead->value)) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($user): void {
            $inner->where('created_by', $user->id)
                ->orWhereHas('subject.teachingAssignments', fn (Builder $q) => $q->where('user_id', $user->id));
        });
    }
}
