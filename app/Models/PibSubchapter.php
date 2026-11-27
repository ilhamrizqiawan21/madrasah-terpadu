<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PibSubchapter extends Model
{
    protected $fillable = ['pib_chapter_id', 'name', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    /**
     * @return BelongsTo<PibChapter, $this>
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(PibChapter::class, 'pib_chapter_id');
    }

    /**
     * @return HasMany<PibMaterial, $this>
     */
    public function materials(): HasMany
    {
        return $this->hasMany(PibMaterial::class)->orderBy('sort_order');
    }
}
