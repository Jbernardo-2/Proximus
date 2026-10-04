<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderConversionSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OrderConversionSuggestionController extends Controller
{
    public function __invoke(Order $order, OrderConversionSuggestionService $suggestions): JsonResponse
    {
        Gate::authorize('update', $order);

        return response()->json(['data' => $suggestions->forOrder($order)]);
    }
}
