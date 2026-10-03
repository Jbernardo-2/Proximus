<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductPresentationRequest;
use App\Http\Requests\UpdateProductPresentationRequest;
use App\Http\Resources\Api\V1\ProductPresentationResource;
use App\Models\Product;
use App\Models\ProductPresentation;
use Illuminate\Http\JsonResponse;

class ProductPresentationController extends Controller
{
    public function store(StoreProductPresentationRequest $request, Product $product): JsonResponse
    {
        $presentation = $product->presentations()->create([
            ...$request->validated(),
            'is_base' => false,
        ]);

        return (new ProductPresentationResource($presentation->load('priceTiers')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Product $product, ProductPresentation $presentation): ProductPresentationResource
    {
        return new ProductPresentationResource($presentation->load('priceTiers'));
    }

    public function update(UpdateProductPresentationRequest $request, Product $product, ProductPresentation $presentation): ProductPresentationResource
    {
        $presentation->update($request->validated());

        return new ProductPresentationResource($presentation->refresh()->load('priceTiers'));
    }

    public function destroy(Product $product, ProductPresentation $presentation): JsonResponse
    {
        if ($presentation->is_base) {
            return response()->json(['message' => 'La presentación base no se puede eliminar.'], 422);
        }

        if ($presentation->productSuppliers()->exists()) {
            return response()->json(['message' => 'La presentación está vinculada a proveedores y no se puede eliminar.'], 422);
        }

        $presentation->delete();

        return response()->json(null, 204);
    }
}
