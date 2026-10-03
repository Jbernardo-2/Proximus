<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesRoute;
use App\Models\Supplier;
use App\Models\User;
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

        if ($user->canManageRoutes()) {
            $metrics['routes'] = SalesRoute::query()->count();
            $metrics['active_routes'] = SalesRoute::query()->active()->count();
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
                ->withCount('routeStops')
                ->latest()
                ->orderByDesc('id')
                ->limit(6)
                ->get() : collect(),
        ]);
    }
}
