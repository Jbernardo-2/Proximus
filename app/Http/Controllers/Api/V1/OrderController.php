<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateOrderAction;
use App\Actions\UpdateOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\OrderStatus;
use App\PaymentTerm;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Order::class);
        /** @var User $user */
        $user = $request->user();
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();
        $paymentTerm = $request->string('payment_term')->toString();
        $dateFrom = $request->string('date_from')->toString();
        $dateTo = $request->string('date_to')->toString();

        $orders = Order::query()
            ->visibleTo($user)
            ->with(['creator'])
            ->withCount('items')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder->where('order_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_code', 'like', "%{$search}%")
                        ->orWhere('route_name', 'like', "%{$search}%");
                });
            })
            ->when(OrderStatus::tryFrom($status) !== null, fn ($query) => $query->where('status', $status))
            ->when(PaymentTerm::tryFrom($paymentTerm) !== null, fn ($query) => $query->where('payment_term', $paymentTerm))
            ->when($this->isDate($dateFrom), fn ($query) => $query->whereDate('order_date', '>=', $dateFrom))
            ->when($this->isDate($dateTo), fn ($query) => $query->whereDate('order_date', '<=', $dateTo))
            ->orderByDesc('order_date')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return OrderResource::collection($orders);
    }

    public function store(StoreOrderRequest $request, CreateOrderAction $createOrder): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $order = $createOrder->handle($request->validated(), $user);

        return (new OrderResource($this->loadOrder($order)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Order $order): OrderResource
    {
        Gate::authorize('view', $order);

        return new OrderResource($this->loadOrder($order));
    }

    public function update(
        UpdateOrderRequest $request,
        Order $order,
        UpdateOrderAction $updateOrder,
    ): OrderResource {
        $updatedOrder = $updateOrder->handle($order, $request->validated());

        return new OrderResource($this->loadOrder($updatedOrder));
    }

    private function loadOrder(Order $order): Order
    {
        return $order->load([
            'creator',
            'confirmedBy',
            'cancelledBy',
            'items' => fn ($query) => $query
                ->with('priceOverriddenBy')
                ->orderBy('product_name')
                ->orderBy('presentation_name')
                ->orderBy('id'),
            'statusHistory' => fn ($query) => $query->with('changedBy')->latest()->orderByDesc('id'),
        ])->loadCount('items');
    }

    private function isDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
