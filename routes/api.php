<?php

use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\MeasurementUnitController;
use App\Http\Controllers\Api\V1\PriceTierController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductConversionPreviewController;
use App\Http\Controllers\Api\V1\ProductPresentationController;
use App\Http\Controllers\Api\V1\ProductSupplierController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('/tokens', [AuthTokenController::class, 'store'])
        ->middleware('throttle:login')
        ->name('tokens.store');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::delete('/tokens/current', [AuthTokenController::class, 'destroy'])->name('tokens.destroy');

        Route::middleware('can:manage-users')->group(function (): void {
            Route::apiResource('users', UserController::class)->except('destroy');
        });

        Route::middleware('can:manage-catalog')->group(function (): void {
            Route::apiResource('categories', CategoryController::class);
            Route::apiResource('brands', BrandController::class);
            Route::apiResource('suppliers', SupplierController::class);
            Route::apiResource('measurement-units', MeasurementUnitController::class)
                ->parameters(['measurement-units' => 'measurement_unit']);
            Route::apiResource('products', ProductController::class);

            Route::scopeBindings()->group(function (): void {
                Route::post('/products/{product}/presentations', [ProductPresentationController::class, 'store'])
                    ->name('products.presentations.store');
                Route::get('/products/{product}/presentations/{presentation}', [ProductPresentationController::class, 'show'])
                    ->name('products.presentations.show');
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
    });
});
