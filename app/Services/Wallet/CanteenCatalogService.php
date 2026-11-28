<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Models\CanteenProduct;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Master kategori produk dan menu kantin.
 */
final class CanteenCatalogService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @template T of ProductCategory|CanteenProduct
     *
     * @param  T|null  $model
     * @param  class-string<T>  $class
     * @param  array<string, mixed>  $data
     * @return T
     */
    public function save(?Model $model, string $class, array $data, string $event, User $actor): Model
    {
        return DB::transaction(function () use ($model, $class, $data, $event, $actor): Model {
            $before = $model?->only(array_keys($data));
            $model ??= new $class;
            $model->fill($data)->save();
            $this->audit->record($event.($before === null ? '.created' : '.updated'), $model, $before, $model->only(array_keys($data)), $actor->id);

            return $model;
        });
    }

    public function deleteCategory(ProductCategory $category, User $actor): void
    {
        if ($category->canteenProducts()->exists()) {
            throw ValidationException::withMessages(['master' => 'Kategori ini masih dipakai menu. Pindahkan atau hapus menunya dulu.']);
        }

        DB::transaction(function () use ($category, $actor): void {
            $this->audit->record('product_category.deleted', $category, $category->only(['unit_id', 'module', 'name']), null, $actor->id);
            $category->delete();
        });
    }

    /** Riwayat transaksi menyimpan salinan nama & harga, jadi menu boleh dihapus. */
    public function deleteProduct(CanteenProduct $product, User $actor): void
    {
        DB::transaction(function () use ($product, $actor): void {
            $this->audit->record('canteen_product.deleted', $product, $product->only(['unit_id', 'name', 'price']), null, $actor->id);
            $product->delete();
        });
    }
}
