<?php

namespace App\Http\Controllers;

use App\Actions\CreateOrderAction;
use App\Actions\UpdateOrderAction;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\RouteStop;
use App\Models\User;
use App\OrderStatus;
use App\PaymentTerm;
use App\Services\OrderConversionSuggestionService;
use App\UserRole;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
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
            ->with(['customer', 'salesRoute', 'salesperson'])
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
            ->paginate(20)
            ->withQueryString();

        return view('orders.index', [
            'orders' => $orders,
            'search' => $search,
            'selectedStatus' => $status,
            'selectedPaymentTerm' => $paymentTerm,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'statuses' => OrderStatus::cases(),
            'paymentTerms' => PaymentTerm::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Order::class);
        /** @var User $user */
        $user = $request->user();
        $routeStops = RouteStop::query()
            ->with(['customer', 'salesRoute.salesperson'])
            ->where('is_active', true)
            ->whereHas('customer', fn ($query) => $query->active())
            ->whereHas('salesRoute', function ($query) use ($user): void {
                $query->active()
                    ->when($user->role === UserRole::Preventista, fn ($routes) => $routes->where('salesperson_id', $user->id));
            })
            ->orderBy('visit_day')
            ->orderBy('visit_order')
            ->orderBy('id')
            ->get();
        $customers = $user->role === UserRole::Preventista
            ? $routeStops->pluck('customer')->unique('id')->sortBy('business_name')->values()
            : Customer::query()->active()->orderBy('business_name')->orderBy('id')->get();
        $salespeople = User::query()
            ->where('role', UserRole::Preventista->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('id')
            ->get();
        $selectedRouteStop = $routeStops->firstWhere('id', $request->string('route_stop_id')->toString());

        return view('orders.create', [
            'routeStops' => $routeStops,
            'customers' => $customers,
            'salespeople' => $salespeople,
            'selectedRouteStop' => $selectedRouteStop,
            'paymentTerms' => PaymentTerm::cases(),
            'isPreventista' => $user->role === UserRole::Preventista,
        ]);
    }

    public function store(StoreOrderRequest $request, CreateOrderAction $createOrder): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $order = $createOrder->handle($request->validated(), $user);

        return redirect()->route('orders.show', $order)->with('success', 'Pedido creado como borrador.');
    }

    public function show(
        Request $request,
        Order $order,
        OrderConversionSuggestionService $conversionSuggestions,
    ): View {
        Gate::authorize('view', $order);
        /** @var User $user */
        $user = $request->user();
        $order->load([
            'customer',
            'salesRoute',
            'routeStop',
            'salesperson',
            'creator',
            'confirmedBy',
            'cancelledBy',
            'items' => fn ($query) => $query
                ->with(['product', 'presentation', 'priceOverriddenBy'])
                ->orderBy('product_name')
                ->orderBy('presentation_name')
                ->orderBy('id'),
            'statusHistory' => fn ($query) => $query->with('changedBy')->latest()->orderByDesc('id'),
        ]);
        $canUpdate = Gate::forUser($user)->allows('update', $order);
        $products = $canUpdate ? Product::query()
            ->active()
            ->with([
                'baseUnit',
                'presentations' => fn ($query) => $query
                    ->active()
                    ->where('is_sellable', true)
                    ->with('priceTiers')
                    ->orderByDesc('conversion_factor')
                    ->orderBy('name'),
            ])
            ->whereHas('presentations', fn ($query) => $query->active()->where('is_sellable', true))
            ->orderBy('name')
            ->orderBy('id')
            ->get() : collect();

        return view('orders.show', [
            'order' => $order,
            'products' => $products,
            'paymentTerms' => PaymentTerm::cases(),
            'canUpdate' => $canUpdate,
            'canOverridePrice' => Gate::forUser($user)->allows('overridePrice', $order),
            'canCancel' => Gate::forUser($user)->allows('cancel', $order),
            'canReopen' => Gate::forUser($user)->allows('reopen', $order),
            'conversionSuggestions' => $canUpdate && $order->items->isNotEmpty()
                ? $conversionSuggestions->forOrder($order)
                : [],
        ]);
    }

    public function update(
        UpdateOrderRequest $request,
        Order $order,
        UpdateOrderAction $updateOrder,
    ): RedirectResponse {
        $updateOrder->handle($order, $request->validated());

        return redirect()->route('orders.show', $order)->with('success', 'Datos del pedido actualizados.');
    }

    private function isDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
