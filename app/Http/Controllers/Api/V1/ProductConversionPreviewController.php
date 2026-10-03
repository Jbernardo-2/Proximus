<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConversionPreviewRequest;
use App\Http\Resources\Api\V1\ConversionSuggestionResource;
use App\Models\Product;
use App\Services\PresentationConversionSuggester;

class ProductConversionPreviewController extends Controller
{
    public function __invoke(
        ConversionPreviewRequest $request,
        Product $product,
        PresentationConversionSuggester $suggester,
    ): ConversionSuggestionResource {
        return new ConversionSuggestionResource(
            $suggester->suggest($product, (string) $request->validated('quantity')),
        );
    }
}
