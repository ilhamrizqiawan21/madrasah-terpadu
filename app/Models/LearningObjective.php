<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tujuan pembelajaran (TP) satu mapel pada satu tingkat kelas.
 *
 * @property int $id
 * @property int $subject_id
 * @property string $grade
 * @property string $code
 * @property string $description
 * @property-read Subject $subject
 */
#[Fillable(['subject_id', 'grade', 'code', 'description'])]
class LearningObjective extends Model
{
    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
