<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConversionPreviewRequest;
use App\Models\Product;
use App\Services\PresentationConversionSuggester;
use Illuminate\Http\JsonResponse;

class ProductConversionPreviewController extends Controller
{
    public function __invoke(
        ConversionPreviewRequest $request,
        Product $product,
        PresentationConversionSuggester $suggester,
    ): JsonResponse {
        return response()->json([
            'data' => $suggester->suggest($product, (string) $request->validated('quantity')),
        ]);
    }
}
