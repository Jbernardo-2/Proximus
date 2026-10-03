<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductSupplierRequest;
use App\Http\Requests\UpdateProductSupplierRequest;
use App\Http\Resources\Api\V1\ProductSupplierResource;
use App\Models\Product;
use App\Models\ProductSupplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProductSupplierController extends Controller
{
    public function store(StoreProductSupplierRequest $request, Product $product): JsonResponse
    {
        $source = DB::transaction(function () use ($request, $product): ProductSupplier {
            $data = $request->validated();

            if ($data['is_preferred']) {
                $product->productSuppliers()->update(['is_preferred' => false]);
            }

            $source = ProductSupplier::withTrashed()
                ->where('supplier_id', $data['supplier_id'])
                ->where('product_presentation_id', $data['product_presentation_id'])
                ->first();

            if ($source === null) {
                return $product->productSuppliers()->create($data);
            }

            $source->restore();
            $source->update([...$data, 'product_id' => $product->id]);

            return $source;
        });

        return (new ProductSupplierResource($source->load(['supplier', 'presentation'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateProductSupplierRequest $request, Product $product, ProductSupplier $productSupplier): ProductSupplierResource
    {
        DB::transaction(function () use ($request, $product, $productSupplier): void {
            $data = $request->validated();

            if ($data['is_preferred']) {
                $product->productSuppliers()->whereKeyNot($productSupplier->id)->update(['is_preferred' => false]);
            }

            $productSupplier->update($data);
        });

        return new ProductSupplierResource($productSupplier->refresh()->load(['supplier', 'presentation']));
    }

    public function destroy(Product $product, ProductSupplier $productSupplier): JsonResponse
    {
        $productSupplier->delete();

        return response()->json(null, 204);
    }
}
