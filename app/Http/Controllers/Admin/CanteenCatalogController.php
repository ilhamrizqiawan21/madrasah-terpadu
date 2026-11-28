<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CanteenProduct;
use App\Models\ProductCategory;
use App\Models\Unit;
use App\Models\User;
use App\Services\Wallet\CanteenCatalogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kantin > Kategori Menu dan Daftar Menu.
 */
class CanteenCatalogController extends Controller
{
    public function __construct(private readonly CanteenCatalogService $service) {}

    public function categories(Request $request): Response
    {
        $unitId = $request->integer('unit') ?: null;
        $module = array_key_exists($request->string('module')->toString(), ProductCategory::MODULES) ? $request->string('module')->toString() : null;
        $search = trim($request->string('search')->toString());

        $paginator = ProductCategory::query()->with('unit:id,name')->withCount('canteenProducts')
            ->when($unitId !== null, fn (Builder $q) => $q->where('unit_id', $unitId))
            ->when($module !== null, fn (Builder $q) => $q->where('module', $module))
            ->when($search !== '', fn (Builder $q) => $q->whereLike('name', "%{$search}%"))
            ->orderBy('unit_id')->orderBy('module')->orderBy('name')->orderBy('id')
            ->paginate(50)->withQueryString();

        return Inertia::render('admin/canteen/categories', [
            'rows' => $this->rows($paginator, fn (ProductCategory $c): array => [
                'id' => $c->id, 'unit_id' => $c->unit_id, 'unit_name' => $c->unit->name, 'module' => $c->module,
                'module_label' => ProductCategory::MODULES[$c->module] ?? $c->module, 'name' => $c->name,
                'products_count' => $c->canteen_products_count,
            ]),
            'filters' => ['search' => $search, 'unit' => $unitId, 'module' => $module],
            'unitOptions' => $this->unitOptions(),
            'moduleOptions' => array_map(fn (string $k, string $v): array => ['value' => $k, 'label' => $v], array_keys(ProductCategory::MODULES), ProductCategory::MODULES),
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $this->service->save(null, ProductCategory::class, $this->categoryData($request, null), 'product_category', $this->actor($request));

        return $this->done('Kategori ditambahkan.');
    }

    public function updateCategory(Request $request, ProductCategory $category): RedirectResponse
    {
        $this->service->save($category, ProductCategory::class, $this->categoryData($request, $category), 'product_category', $this->actor($request));

        return $this->done('Kategori diperbarui.');
    }

    public function destroyCategory(Request $request, ProductCategory $category): RedirectResponse
    {
        $this->service->deleteCategory($category, $this->actor($request));

        return $this->done('Kategori dihapus.');
    }

    public function products(Request $request): Response
    {
        $unitId = $request->integer('unit') ?: null;
        $categoryId = $request->integer('category') ?: null;
        $search = trim($request->string('search')->toString());

        $paginator = CanteenProduct::query()->with(['unit:id,name', 'category:id,name'])
            ->when($unitId !== null, fn (Builder $q) => $q->where('unit_id', $unitId))
            ->when($categoryId !== null, fn (Builder $q) => $q->where('product_category_id', $categoryId))
            ->when($search !== '', fn (Builder $q) => $q->whereLike('name', "%{$search}%"))
            ->orderBy('unit_id')->orderBy('product_category_id')->orderBy('name')->orderBy('id')
            ->paginate(50)->withQueryString();

        return Inertia::render('admin/canteen/products', [
            'rows' => $this->rows($paginator, fn (CanteenProduct $p): array => [
                'id' => $p->id, 'unit_name' => $p->unit->name, 'category_id' => $p->product_category_id,
                'category_name' => $p->category->name, 'name' => $p->name, 'price' => $p->price,
                'stock' => $p->stock, 'is_active' => $p->is_active,
            ]),
            'filters' => ['search' => $search, 'unit' => $unitId, 'category' => $categoryId],
            'unitOptions' => $this->unitOptions(),
            'categoryOptions' => array_values(ProductCategory::query()->with('unit:id,code')->where('module', 'canteen')
                ->when($unitId !== null, fn (Builder $q) => $q->where('unit_id', $unitId))
                ->orderBy('unit_id')->orderBy('name')->get()
                ->map(fn (ProductCategory $c): array => ['value' => (string) $c->id, 'label' => "{$c->name} ({$c->unit->code})"])->all()),
        ]);
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        $this->service->save(null, CanteenProduct::class, $this->productData($request, null), 'canteen_product', $this->actor($request));

        return $this->done('Menu ditambahkan.');
    }

    public function updateProduct(Request $request, CanteenProduct $product): RedirectResponse
    {
        $this->service->save($product, CanteenProduct::class, $this->productData($request, $product), 'canteen_product', $this->actor($request));

        return $this->done('Menu diperbarui.');
    }

    public function destroyProduct(Request $request, CanteenProduct $product): RedirectResponse
    {
        $this->service->deleteProduct($product, $this->actor($request));

        return $this->done('Menu dihapus.');
    }

    /**
     * @return array{unit_id: int, module: string, name: string}
     */
    private function categoryData(Request $request, ?ProductCategory $category): array
    {
        $data = $request->validate([
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('is_active', true)],
            'module' => ['required', Rule::in(array_keys(ProductCategory::MODULES))],
            'name' => ['required', 'string', 'max:100', Rule::unique('product_categories', 'name')
                ->where('unit_id', $request->integer('unit_id'))->where('module', $request->string('module')->toString())->ignore($category?->id)],
        ], [], ['unit_id' => 'unit', 'module' => 'peruntukan', 'name' => 'nama kategori']);

        return ['unit_id' => (int) $data['unit_id'], 'module' => (string) $data['module'], 'name' => trim((string) $data['name'])];
    }

    /**
     * @return array{unit_id: int, product_category_id: int, name: string, price: int, stock: int|null, is_active: bool}
     */
    private function productData(Request $request, ?CanteenProduct $product): array
    {
        $data = $request->validate([
            'product_category_id' => ['required', 'integer', Rule::exists('product_categories', 'id')->where('module', 'canteen')],
            'name' => ['required', 'string', 'max:100'],
            'price' => ['required', 'integer', 'min:100', 'max:1000000'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['required', 'in:0,1'],
        ], [], ['product_category_id' => 'kategori', 'name' => 'nama menu', 'price' => 'harga jual', 'stock' => 'stok porsi', 'is_active' => 'status']);

        // Unit menu mengikuti unit kategorinya.
        $unitId = (int) ProductCategory::query()->whereKey((int) $data['product_category_id'])->value('unit_id');
        $name = trim((string) $data['name']);
        $duplicate = CanteenProduct::query()->where('unit_id', $unitId)->where('name', $name)
            ->when($product !== null, fn (Builder $q) => $q->whereKeyNot($product->id))->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['name' => 'Nama menu ini sudah ada di unit yang sama.']);
        }

        return [
            'unit_id' => $unitId, 'product_category_id' => (int) $data['product_category_id'], 'name' => $name,
            'price' => (int) $data['price'], 'stock' => isset($data['stock']) && $data['stock'] !== '' ? (int) $data['stock'] : null,
            'is_active' => (string) $data['is_active'] === '1',
        ];
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  LengthAwarePaginator<int, T>  $paginator
     * @param  callable(T): array<string, mixed>  $map
     * @return array{data: list<array<string, mixed>>, meta: array<string, int|null>}
     */
    private function rows(LengthAwarePaginator $paginator, callable $map): array
    {
        return [
            'data' => array_values(array_map($map, $paginator->items())),
            'meta' => [
                'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(),
                'total' => $paginator->total(), 'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(),
            ],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function unitOptions(): array
    {
        return array_values(Unit::query()->where('is_active', true)->orderBy('id')->get(['id', 'name'])
            ->map(fn (Unit $u): array => ['value' => (string) $u->id, 'label' => $u->name])->all());
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
