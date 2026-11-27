<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Capaian satu siswa pada satu dimensi. `computed_*` hanya diubah oleh hitung ulang;
 * `final_level`, `description` (bila `description_edited`), dan observasi adalah koreksi manusia.
 *
 * @property int $id
 * @property int $character_review_id
 * @property int $student_id
 * @property int $character_dimension_id
 * @property string|null $computed_score
 * @property string|null $computed_level
 * @property string|null $final_level
 * @property string|null $override_reason
 * @property int|null $overridden_by
 * @property int|null $observation_level
 * @property string|null $description
 * @property bool $description_edited
 * @property array<string, mixed> $inputs
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CharacterReview $review
 * @property-read Student $student
 * @property-read CharacterDimension $dimension
 */
#[Fillable(['character_review_id', 'student_id', 'character_dimension_id', 'computed_score', 'computed_level', 'final_level', 'override_reason', 'overridden_by', 'observation_level', 'description', 'description_edited', 'inputs'])]
class CharacterAssessment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'computed_score' => 'decimal:2',
            'observation_level' => 'integer',
            'description_edited' => 'boolean',
            'inputs' => 'array',
        ];
    }

    /** Capaian yang berlaku: koreksi manusia bila ada, selain itu hasil hitung. */
    public function effectiveLevel(): ?string
    {
        return $this->final_level ?? $this->computed_level;
    }

    /**
     * @return BelongsTo<CharacterReview, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(CharacterReview::class, 'character_review_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<CharacterDimension, $this>
     */
    public function dimension(): BelongsTo
    {
        return $this->belongsTo(CharacterDimension::class, 'character_dimension_id');
    }
}
