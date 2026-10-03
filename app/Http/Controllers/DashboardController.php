<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard', [
            'metrics' => [
                'products' => Product::query()->count(),
                'active_products' => Product::query()->active()->count(),
                'categories' => Category::query()->count(),
                'brands' => Brand::query()->count(),
                'suppliers' => Supplier::query()->count(),
            ],
            'recentProducts' => Product::query()
                ->with(['category', 'basePresentation'])
                ->latest()
                ->orderByDesc('id')
                ->limit(6)
                ->get(),
        ]);
    }
}
