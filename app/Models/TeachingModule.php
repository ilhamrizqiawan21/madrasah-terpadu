<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Berkas modul ajar (RPP) mingguan seorang guru beserta hasil supervisi.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $academic_year_id
 * @property int $user_id
 * @property int $week_number
 * @property string $title
 * @property string $file_path
 * @property string $original_name
 * @property string $status pending|approved|revision
 * @property int|null $reviewer_id
 * @property string|null $review_notes
 * @property CarbonImmutable|null $reviewed_at
 * @property-read Unit $unit
 * @property-read User $teacher
 * @property-read User|null $reviewer
 */
#[Fillable(['unit_id', 'academic_year_id', 'user_id', 'week_number', 'title', 'file_path', 'original_name', 'status', 'reviewer_id', 'review_notes', 'reviewed_at'])]
class TeachingModule extends Model
{
    public const array STATUSES = ['pending' => 'Menunggu review', 'approved' => 'Disetujui', 'revision' => 'Perlu revisi'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['week_number' => 'integer', 'reviewed_at' => 'immutable_datetime'];
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
