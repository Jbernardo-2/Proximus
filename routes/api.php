<?php

use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\CancelInventoryCountController;
use App\Http\Controllers\Api\V1\CancelInventoryDocumentController;
use App\Http\Controllers\Api\V1\CancelOrderController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ConfirmOrderController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\InventoryCountController;
use App\Http\Controllers\Api\V1\InventoryDocumentController;
use App\Http\Controllers\Api\V1\InventoryDocumentItemController;
use App\Http\Controllers\Api\V1\InventoryMovementController;
use App\Http\Controllers\Api\V1\InventoryStockController;
use App\Http\Controllers\Api\V1\MeasurementUnitController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\OrderConversionSuggestionController;
use App\Http\Controllers\Api\V1\OrderItemController;
use App\Http\Controllers\Api\V1\OrderItemQuoteController;
use App\Http\Controllers\Api\V1\PostInventoryCountController;
use App\Http\Controllers\Api\V1\PostInventoryDocumentController;
use App\Http\Controllers\Api\V1\PriceTierController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductConversionPreviewController;
use App\Http\Controllers\Api\V1\ProductPresentationController;
use App\Http\Controllers\Api\V1\ProductSupplierController;
use App\Http\Controllers\Api\V1\ReopenOrderController;
use App\Http\Controllers\Api\V1\RouteStopController;
use App\Http\Controllers\Api\V1\SalesRouteController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->middleware('throttle:api')->group(function (): void {
    Route::post('/tokens', [AuthTokenController::class, 'store'])
        ->middleware('throttle:login')
        ->name('tokens.store');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::delete('/tokens/current', [AuthTokenController::class, 'destroy'])->name('tokens.destroy');

        Route::middleware(['abilities:users:manage', 'can:manage-users'])->group(function (): void {
            Route::apiResource('users', UserController::class)->except('destroy');
        });

        Route::middleware(['abilities:customers:manage', 'can:manage-customers'])->group(function (): void {
            Route::apiResource('customers', CustomerController::class);
        });

        Route::middleware(['abilities:routes:view', 'can:view-routes'])->group(function (): void {
            Route::get('/routes', [SalesRouteController::class, 'index'])->name('routes.index');
            Route::get('/routes/{salesRoute}', [SalesRouteController::class, 'show'])->name('routes.show');

            Route::scopeBindings()->group(function (): void {
                Route::get('/routes/{salesRoute}/stops/{stop}', [RouteStopController::class, 'show'])
                    ->name('routes.stops.show');
            });
        });

        Route::middleware(['abilities:routes:manage', 'can:manage-routes'])->group(function (): void {
            Route::post('/routes', [SalesRouteController::class, 'store'])->name('routes.store');
            Route::put('/routes/{salesRoute}', [SalesRouteController::class, 'update'])->name('routes.update');
            Route::delete('/routes/{salesRoute}', [SalesRouteController::class, 'destroy'])->name('routes.destroy');

            Route::scopeBindings()->group(function (): void {
                Route::post('/routes/{salesRoute}/stops', [RouteStopController::class, 'store'])
                    ->name('routes.stops.store');
                Route::put('/routes/{salesRoute}/stops/{stop}', [RouteStopController::class, 'update'])
                    ->name('routes.stops.update');
                Route::delete('/routes/{salesRoute}/stops/{stop}', [RouteStopController::class, 'destroy'])
                    ->name('routes.stops.destroy');
            });
        });

        Route::middleware(['abilities:orders:view', 'can:view-orders'])->group(function (): void {
            Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
            Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        });

        Route::middleware(['abilities:orders:manage', 'can:manage-orders'])->group(function (): void {
            Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
            Route::put('/orders/{order}', [OrderController::class, 'update'])->name('orders.update');
            Route::get('/orders/{order}/item-quote', OrderItemQuoteController::class)
                ->name('orders.item-quote');
            Route::get('/orders/{order}/conversion-suggestions', OrderConversionSuggestionController::class)
                ->name('orders.conversion-suggestions');
            Route::post('/orders/{order}/confirm', ConfirmOrderController::class)->name('orders.confirm');

            Route::scopeBindings()->group(function (): void {
                Route::post('/orders/{order}/items', [OrderItemController::class, 'store'])
                    ->name('orders.items.store');
                Route::put('/orders/{order}/items/{orderItem}', [OrderItemController::class, 'update'])
                    ->name('orders.items.update');
                Route::delete('/orders/{order}/items/{orderItem}', [OrderItemController::class, 'destroy'])
                    ->name('orders.items.destroy');
            });
        });

        Route::middleware(['abilities:orders:lifecycle', 'can:view-orders'])->group(function (): void {
            Route::post('/orders/{order}/cancel', CancelOrderController::class)->name('orders.cancel');
            Route::post('/orders/{order}/reopen', ReopenOrderController::class)->name('orders.reopen');
        });

        Route::middleware(['abilities:inventory:view', 'can:view-inventory'])->group(function (): void {
            Route::get('/warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
            Route::get('/warehouses/{warehouse}', [WarehouseController::class, 'show'])->name('warehouses.show');
            Route::get('/inventory/stocks', [InventoryStockController::class, 'index'])->name('inventory.stocks.index');
            Route::get('/inventory/stocks/{inventoryStock}', [InventoryStockController::class, 'show'])->name('inventory.stocks.show');
        });

        Route::middleware(['abilities:inventory:operate', 'can:operate-inventory'])->group(function (): void {
            Route::get('/inventory/movements', [InventoryMovementController::class, 'index'])->name('inventory.movements.index');
            Route::get('/inventory/movements/{inventoryMovement}', [InventoryMovementController::class, 'show'])->name('inventory.movements.show');

            Route::get('/inventory/documents', [InventoryDocumentController::class, 'index'])->name('inventory.documents.index');
            Route::post('/inventory/documents', [InventoryDocumentController::class, 'store'])->name('inventory.documents.store');
            Route::get('/inventory/documents/{inventoryDocument}', [InventoryDocumentController::class, 'show'])->name('inventory.documents.show');
            Route::put('/inventory/documents/{inventoryDocument}', [InventoryDocumentController::class, 'update'])->name('inventory.documents.update');
            Route::post('/inventory/documents/{inventoryDocument}/post', PostInventoryDocumentController::class)->name('inventory.documents.post');
            Route::post('/inventory/documents/{inventoryDocument}/cancel', CancelInventoryDocumentController::class)->name('inventory.documents.cancel');

            Route::scopeBindings()->group(function (): void {
                Route::post('/inventory/documents/{inventoryDocument}/items', [InventoryDocumentItemController::class, 'store'])->name('inventory.documents.items.store');
                Route::put('/inventory/documents/{inventoryDocument}/items/{inventoryDocumentItem}', [InventoryDocumentItemController::class, 'update'])->name('inventory.documents.items.update');
                Route::delete('/inventory/documents/{inventoryDocument}/items/{inventoryDocumentItem}', [InventoryDocumentItemController::class, 'destroy'])->name('inventory.documents.items.destroy');
            });

            Route::get('/inventory/counts', [InventoryCountController::class, 'index'])->name('inventory.counts.index');
            Route::post('/inventory/counts', [InventoryCountController::class, 'store'])->name('inventory.counts.store');
            Route::get('/inventory/counts/{inventoryCount}', [InventoryCountController::class, 'show'])->name('inventory.counts.show');
            Route::put('/inventory/counts/{inventoryCount}', [InventoryCountController::class, 'update'])->name('inventory.counts.update');
            Route::post('/inventory/counts/{inventoryCount}/post', PostInventoryCountController::class)->name('inventory.counts.post');
            Route::post('/inventory/counts/{inventoryCount}/cancel', CancelInventoryCountController::class)->name('inventory.counts.cancel');
        });

        Route::middleware(['abilities:inventory:configure', 'can:configure-inventory'])->group(function (): void {
            Route::post('/warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
            Route::put('/warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('warehouses.update');
            Route::put('/inventory/stocks/{inventoryStock}', [InventoryStockController::class, 'update'])->name('inventory.stocks.update');
        });

        Route::middleware(['abilities:catalog:manage', 'can:manage-catalog'])->group(function (): void {
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
