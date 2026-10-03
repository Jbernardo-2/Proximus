<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePriceTierRequest;
use App\Http\Requests\UpdatePriceTierRequest;
use App\Http\Resources\Api\V1\PriceTierResource;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductPresentation;
use Illuminate\Http\JsonResponse;

class PriceTierController extends Controller
{
    public function store(StorePriceTierRequest $request, Product $product, ProductPresentation $presentation): JsonResponse
    {
        $priceTier = $presentation->priceTiers()->create($request->validated());

        return (new PriceTierResource($priceTier))->response()->setStatusCode(201);
    }

    public function update(
        UpdatePriceTierRequest $request,
        Product $product,
        ProductPresentation $presentation,
        PriceTier $priceTier,
    ): PriceTierResource {
        $priceTier->update($request->validated());

        return new PriceTierResource($priceTier->refresh());
    }

    public function destroy(Product $product, ProductPresentation $presentation, PriceTier $priceTier): JsonResponse
    {
        $priceTier->delete();

        return response()->json(null, 204);
    }
}
