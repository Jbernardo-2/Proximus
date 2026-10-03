<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateProductAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = $request->string('search')->trim()->toString();
        $products = Product::query()
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
            ->when($request->has('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request, CreateProductAction $createProduct): JsonResponse
    {
        $product = $createProduct->handle($request->validated(), $request->file('image'));

        return (new ProductResource($product))->response()->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($this->loadProduct($product));
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
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

        return new ProductResource($this->loadProduct($product->refresh()));
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(null, 204);
    }

    private function loadProduct(Product $product): Product
    {
        return $product->load([
            'category',
            'brand',
            'baseUnit',
            'basePresentation.priceTiers',
            'presentations' => fn ($query) => $query->orderByDesc('conversion_factor')->orderBy('name'),
            'presentations.priceTiers' => fn ($query) => $query->orderBy('min_quantity'),
            'productSuppliers.supplier',
            'productSuppliers.presentation',
        ]);
    }
}
