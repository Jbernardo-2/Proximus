<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MeasurementUnitController;
use App\Http\Controllers\PriceTierController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductConversionPreviewController;
use App\Http\Controllers\ProductPresentationController;
use App\Http\Controllers\ProductSupplierController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'can:manage-catalog'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::resource('categories', CategoryController::class)->except(['show']);
    Route::resource('brands', BrandController::class)->except(['show']);
    Route::resource('suppliers', SupplierController::class)->except(['show']);
    Route::resource('measurement-units', MeasurementUnitController::class)
        ->parameters(['measurement-units' => 'measurement_unit'])
        ->except(['show']);
    Route::resource('products', ProductController::class);

    Route::scopeBindings()->group(function (): void {
        Route::post('/products/{product}/presentations', [ProductPresentationController::class, 'store'])
            ->name('products.presentations.store');
        Route::put('/products/{product}/presentations/{presentation}', [ProductPresentationController::class, 'update'])
            ->name('products.presentations.update');
        Route::delete('/products/{product}/presentations/{presentation}', [ProductPresentationController::class, 'destroy'])
            ->name('products.presentations.destroy');

        Route::post('/products/{product}/presentations/{presentation}/price-tiers', [PriceTierController::class, 'store'])
            ->name('products.presentations.price-tiers.store');
        Route::put('/products/{product}/presentations/{presentation}/price-tiers/{priceTier}', [PriceTierController::class, 'update'])
            ->name('products.presentations.price-tiers.update');
        Route::delete('/products/{product}/presentations/{presentation}/price-tiers/{priceTier}', [PriceTierController::class, 'destroy'])
            ->name('products.presentations.price-tiers.destroy');

        Route::post('/products/{product}/suppliers', [ProductSupplierController::class, 'store'])
            ->name('products.suppliers.store');
        Route::put('/products/{product}/suppliers/{productSupplier}', [ProductSupplierController::class, 'update'])
            ->name('products.suppliers.update');
        Route::delete('/products/{product}/suppliers/{productSupplier}', [ProductSupplierController::class, 'destroy'])
            ->name('products.suppliers.destroy');

        Route::get('/products/{product}/conversion-preview', ProductConversionPreviewController::class)
            ->name('products.conversion-preview');
    });
});

Route::middleware(['auth', 'can:manage-users'])->group(function (): void {
    Route::resource('users', UserController::class)->except(['show', 'destroy']);
});
