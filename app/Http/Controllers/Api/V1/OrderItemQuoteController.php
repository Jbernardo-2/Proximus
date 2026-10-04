<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderItemQuoteRequest;
use App\Models\Order;
use App\Models\ProductPresentation;
use App\Services\OrderItemQuoteService;
use Illuminate\Http\JsonResponse;

class OrderItemQuoteController extends Controller
{
    public function __invoke(
        OrderItemQuoteRequest $request,
        Order $order,
        OrderItemQuoteService $quoteService,
    ): JsonResponse {
        $presentation = ProductPresentation::query()
            ->findOrFail($request->validated('product_presentation_id'));

        return response()->json([
            'data' => $quoteService->quote($order, $presentation, (string) $request->validated('quantity')),
        ]);
    }
}
