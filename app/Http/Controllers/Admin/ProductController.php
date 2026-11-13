<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductType;
use App\Enums\TaxClass;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('q')->toString();

        $products = Product::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('sku', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Product $product) => [
                'id' => $product->id,
                'title' => $product->translate('title', config('shop.locales')[0]),
                'sku' => $product->sku,
                'type' => $product->type->value,
                'price_cents' => $product->price_cents,
                'stock' => $product->track_stock ? $product->stock : null,
                'is_active' => $product->is_active,
                'image' => $product->imageUrl(),
            ]);

        return Inertia::render('admin/products/index', [
            'products' => $products,
            'filters' => ['q' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/products/form', $this->formProps(null));
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = Product::query()->create($request->productAttributes());
        $this->syncExtras($product, $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product created.')]);

        return to_route('admin.products.edit', $product);
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('admin/products/form', $this->formProps($product));
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->productAttributes());
        $this->syncExtras($product, $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product updated.')]);

        return to_route('admin.products.edit', $product);
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product deleted.')]);

        return to_route('admin.products.index');
    }

    private function syncExtras(Product $product, ProductRequest $request): void
    {
        $images = $request->input('existing_images', []);

        foreach ($request->file('images', []) as $file) {
            $images[] = $file->store('products', 'public');
        }

        foreach (array_diff($product->images ?? [], $images) as $removed) {
            Storage::disk('public')->delete($removed);
        }

        $product->update(['images' => array_values($images)]);

        foreach ($request->input('group_prices', []) as $groupId => $price) {
            if ($price === null || $price === '') {
                ProductPrice::query()->where('product_id', $product->id)->where('customer_group_id', $groupId)->delete();

                continue;
            }

            ProductPrice::query()->updateOrCreate(
                ['product_id' => $product->id, 'customer_group_id' => $groupId],
                ['price_cents' => (int) round((float) str_replace(',', '.', $price) * 100)],
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(?Product $product): array
    {
        $product?->load('prices');

        return [
            'product' => $product === null ? null : [
                'id' => $product->id,
                'type' => $product->type->value,
                'sku' => $product->sku,
                'slug' => $product->slug,
                'title' => $product->title,
                'description' => $product->description ?? [],
                'tax_class' => $product->tax_class->value,
                'price' => number_format($product->price_cents / 100, 2, '.', ''),
                'stock' => $product->stock,
                'track_stock' => $product->track_stock,
                'weight_grams' => $product->weight_grams,
                'images' => array_map(fn (string $path) => ['path' => $path, 'url' => asset('storage/'.$path)], $product->images ?? []),
                'specs' => $product->specs ?? [],
                'is_active' => $product->is_active,
                'published_at' => $product->published_at?->format('Y-m-d'),
                'group_prices' => $product->prices->mapWithKeys(fn (ProductPrice $price) => [
                    $price->customer_group_id => number_format($price->price_cents / 100, 2, '.', ''),
                ]),
            ],
            'locales' => config('shop.locales'),
            'groups' => CustomerGroup::query()->orderBy('id')->get(['id', 'name', 'code', 'is_default']),
            'types' => collect(ProductType::cases())->map(fn (ProductType $type) => [
                'value' => $type->value,
                'fields' => $type->attributeFields(),
            ]),
            'tax_classes' => array_map(fn (TaxClass $class) => $class->value, TaxClass::cases()),
        ];
    }
}
