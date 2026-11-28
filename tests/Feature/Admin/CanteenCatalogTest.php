<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Role as RoleEnum;
use App\Models\CanteenProduct;
use App\Models\ProductCategory;
use App\Models\Unit;
use App\Models\User;
use App\Services\Features\FeatureFlags;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleEnum::Admin->value);
    app(FeatureFlags::class)->update([Feature::Wallet->value => true], $this->admin);
    $this->unit = Unit::factory()->create(['code' => 'MI']);
});

test('kategori dan menu kantin: unit menu mengikuti kategori, nama unik, kategori terpakai tidak bisa dihapus', function (): void {
    $this->actingAs($this->admin)->post(route('admin.canteen-categories.store'), [
        'unit_id' => $this->unit->id, 'module' => 'canteen', 'name' => 'Minuman',
    ])->assertSessionHasNoErrors();
    $category = ProductCategory::query()->sole();

    $menu = ['product_category_id' => $category->id, 'name' => 'Es Teh', 'price' => 3000, 'stock' => '', 'is_active' => '1'];
    $this->actingAs($this->admin)->post(route('admin.canteen-products.store'), $menu)->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post(route('admin.canteen-products.store'), $menu)->assertSessionHasErrors('name');
    $this->actingAs($this->admin)->post(route('admin.canteen-products.store'), [...$menu, 'name' => 'Air', 'price' => 50])->assertSessionHasErrors('price');

    $product = CanteenProduct::query()->sole();
    expect($product->unit_id)->toBe($this->unit->id)->and($product->stock)->toBeNull();

    $this->actingAs($this->admin)->delete(route('admin.canteen-categories.destroy', $category))->assertSessionHasErrors('master');

    $this->actingAs($this->admin)->get(route('admin.canteen-products.index'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/canteen/products')
            ->where('rows.data.0.price', 3000)->where('categoryOptions.0.label', 'Minuman (MI)'));
});

test('kategori koperasi tidak bisa dipakai untuk menu kantin dan kasir kantin tidak bisa membuka master', function (): void {
    $coop = ProductCategory::query()->create(['unit_id' => $this->unit->id, 'module' => 'cooperative', 'name' => 'Seragam']);
    $this->actingAs($this->admin)->post(route('admin.canteen-products.store'), [
        'product_category_id' => $coop->id, 'name' => 'Baju', 'price' => 50000, 'is_active' => '1',
    ])->assertSessionHasErrors('product_category_id');

    $cashier = User::factory()->create();
    $cashier->assignRole(RoleEnum::CanteenCashier->value);
    $this->actingAs($cashier)->get(route('admin.canteen-products.index'))->assertForbidden();
});
