<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PibMaterial extends Model
{
    protected $fillable = ['pib_subchapter_id', 'name', 'weight', 'sort_order'];

    protected function casts(): array
    {
        return [
            'weight' => 'float',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PibSubchapter, $this>
     */
    public function subchapter(): BelongsTo
    {
        return $this->belongsTo(PibSubchapter::class, 'pib_subchapter_id');
    }
}
