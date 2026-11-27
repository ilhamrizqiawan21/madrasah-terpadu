<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PibChapter extends Model
{
    protected $fillable = ['unit_id', 'name', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    /**
     * @return HasMany<PibSubchapter, $this>
     */
    public function subchapters(): HasMany
    {
        return $this->hasMany(PibSubchapter::class)->orderBy('sort_order');
    }
}
