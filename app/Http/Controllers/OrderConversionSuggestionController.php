<?php

namespace App\Http\Controllers;

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
