<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RubricCriterion extends Model
{
    protected $table = 'rubric_criteria';

    protected $fillable = [
        'rubric_version_id',
        'name',
        'mode',
        'weight',
        'max_score',
        'deduction_per_error',
        'pass_threshold',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'float',
            'max_score' => 'integer',
            'deduction_per_error' => 'float',
            'pass_threshold' => 'float',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<RubricVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(RubricVersion::class, 'rubric_version_id');
    }
}
