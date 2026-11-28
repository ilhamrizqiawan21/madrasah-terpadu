<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Menu kantin dengan harga jual (rupiah) dan stok porsi opsional (null = tidak dihitung).
 *
 * @property int $id
 * @property int $unit_id
 * @property int $product_category_id
 * @property string $name
 * @property int $price
 * @property int|null $stock
 * @property bool $is_active
 * @property-read Unit $unit
 * @property-read ProductCategory $category
 */
#[Fillable(['unit_id', 'product_category_id', 'name', 'price', 'stock', 'is_active'])]
class CanteenProduct extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['price' => 'integer', 'stock' => 'integer', 'is_active' => 'boolean'];
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<ProductCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }
}
