<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\Product;
use App\Models\SalesRoute;
use App\Models\Supplier;
use App\Models\User;
use App\OrderStatus;
use App\UserRole;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $metrics = [];

        if ($user->canManageCustomers()) {
            $metrics['customers'] = Customer::query()->count();
            $metrics['active_customers'] = Customer::query()->active()->count();
        }

        if ($user->canViewRoutes()) {
            $routeQuery = SalesRoute::query()
                ->when($user->role === UserRole::Preventista, fn ($query) => $query->where('salesperson_id', $user->id));
            $metrics['routes'] = (clone $routeQuery)->count();
            $metrics['active_routes'] = (clone $routeQuery)->active()->count();
        }

        if ($user->canViewOrders()) {
            $orderQuery = Order::query()->visibleTo($user);
            $metrics['orders'] = (clone $orderQuery)->count();
            $metrics['draft_orders'] = (clone $orderQuery)->where('status', OrderStatus::Draft->value)->count();
            $metrics['confirmed_orders'] = (clone $orderQuery)->where('status', OrderStatus::Confirmed->value)->count();
        }

        if ($user->canViewInventory()) {
            $metrics['inventory_products'] = InventoryStock::query()->count();
            $metrics['inventory_shortages'] = InventoryStock::query()
                ->whereColumn('quantity_on_hand', '<', 'quantity_reserved')
                ->count();
            $metrics['inventory_low'] = InventoryStock::query()
                ->whereColumn('quantity_on_hand', '>=', 'quantity_reserved')
                ->whereRaw('(quantity_on_hand - quantity_reserved) <= reorder_point')
                ->count();
        }

        if ($user->canManageCatalog()) {
            $metrics['products'] = Product::query()->count();
            $metrics['active_products'] = Product::query()->active()->count();
            $metrics['categories'] = Category::query()->count();
            $metrics['brands'] = Brand::query()->count();
            $metrics['suppliers'] = Supplier::query()->count();
        }

        return view('dashboard', [
            'metrics' => $metrics,
            'recentProducts' => $user->canManageCatalog() ? Product::query()
                ->with(['category', 'basePresentation'])
                ->latest()
                ->orderByDesc('id')
                ->limit(6)
                ->get() : collect(),
            'recentCustomers' => $user->canManageCustomers() ? Customer::query()
                ->withCount(['routeStops' => fn ($query) => $query->visibleTo($user)])
                ->latest()
                ->orderByDesc('id')
                ->limit(6)
                ->get() : collect(),
            'recentOrders' => $user->canViewOrders() ? Order::query()
                ->visibleTo($user)
                ->latest()
                ->orderByDesc('id')
                ->limit(6)
                ->get() : collect(),
        ]);
    }
}
