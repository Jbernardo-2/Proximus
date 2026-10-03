<?php

namespace App\Http\Controllers;

use App\Actions\CreateProductAction;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\MeasurementUnit;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->toString();

        return view('catalog.products.index', [
            'products' => Product::query()
                ->with(['category', 'brand', 'baseUnit', 'basePresentation'])
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($builder) use ($search): void {
                        $builder->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%")
                            ->orWhereHas('presentations', fn ($presentations) => $presentations->where('barcode', 'like', "%{$search}%"));
                    });
                })
                ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->input('category_id')))
                ->when($request->filled('brand_id'), fn ($query) => $query->where('brand_id', $request->input('brand_id')))
                ->when($request->filled('status'), function ($query) use ($request): void {
                    $query->where('is_active', $request->string('status')->toString() === 'active');
                })
                ->orderBy('name')
                ->orderBy('id')
                ->paginate(15)
                ->withQueryString(),
            'categories' => Category::query()->active()->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::query()->active()->orderBy('name')->get(['id', 'name']),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('catalog.products.form', [
            'product' => new Product,
            ...$this->formOptions(),
        ]);
    }

    public function store(StoreProductRequest $request, CreateProductAction $createProduct): RedirectResponse
    {
        $product = $createProduct->handle($request->validated(), $request->file('image'));

        return redirect()->route('products.show', $product)->with('success', 'Producto creado con su presentación base.');
    }

    public function show(Product $product): View
    {
        $product->load([
            'category',
            'brand',
            'baseUnit',
            'presentations' => fn ($query) => $query->orderByDesc('conversion_factor')->orderBy('name'),
            'presentations.priceTiers' => fn ($query) => $query->orderBy('min_quantity')->orderBy('starts_at'),
            'productSuppliers.supplier',
            'productSuppliers.presentation',
        ]);

        return view('catalog.products.show', [
            'product' => $product,
            'suppliers' => Supplier::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function edit(Product $product): View
    {
        return view('catalog.products.form', [
            'product' => $product,
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $oldImage = $product->image_path;
        $newImage = $request->file('image')?->store('products', 'public');
        $data = $request->safe()->except(['image', 'remove_image']);

        if ($newImage !== null) {
            $data['image_path'] = $newImage;
        } elseif ($request->boolean('remove_image')) {
            $data['image_path'] = null;
        }

        try {
            $product->update($data);
        } catch (Throwable $exception) {
            if ($newImage !== null) {
                Storage::disk('public')->delete($newImage);
            }

            throw $exception;
        }

        if ($oldImage !== null && $oldImage !== $product->image_path) {
            Storage::disk('public')->delete($oldImage);
        }

        return redirect()->route('products.show', $product)->with('success', 'Producto actualizado correctamente.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('products.index')->with('success', 'Producto archivado correctamente.');
    }

    private function formOptions(): array
    {
        return [
            'categories' => Category::query()->active()->orderBy('name')->get(),
            'brands' => Brand::query()->active()->orderBy('name')->get(),
            'units' => MeasurementUnit::query()->active()->orderBy('name')->get(),
        ];
    }
}
