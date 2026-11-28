<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kategori produk per unit; `module` = canteen (menu kantin) atau cooperative (koperasi: seragam, buku, ATK).
 *
 * @property int $id
 * @property int $unit_id
 * @property string $module
 * @property string $name
 * @property-read Unit $unit
 */
#[Fillable(['unit_id', 'module', 'name'])]
class ProductCategory extends Model
{
    public const array MODULES = ['canteen' => 'Kantin (makanan, minuman, snack)', 'cooperative' => 'Koperasi (seragam, buku, ATK)'];

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return HasMany<CanteenProduct, $this>
     */
    public function canteenProducts(): HasMany
    {
        return $this->hasMany(CanteenProduct::class);
    }
}
