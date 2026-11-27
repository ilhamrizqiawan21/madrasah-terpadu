<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FeeRecurrence;
use Database\Factories\FeeTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Master jenis tagihan sekolah (T-601). Ditulis hanya lewat App\Services\Billing\FeeTypeService.
 *
 * @property int $id
 * @property int|null $unit_id
 * @property string $name
 * @property string $category
 * @property FeeRecurrence $recurrence
 * @property int $default_amount
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Unit|null $unit
 */
#[Fillable(['unit_id', 'name', 'category', 'recurrence', 'default_amount', 'is_active'])]
class FeeType extends Model
{
    /** @use HasFactory<FeeTypeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recurrence' => FeeRecurrence::class,
            'default_amount' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
