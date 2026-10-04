<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CancelDeliveryRunController;
use App\Http\Controllers\CancelInventoryCountController;
use App\Http\Controllers\CancelInventoryDocumentController;
use App\Http\Controllers\CancelOrderController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CompleteDeliveryStopController;
use App\Http\Controllers\ConfirmDeliveryLoadController;
use App\Http\Controllers\ConfirmOrderController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryPaymentController;
use App\Http\Controllers\DeliveryRunController;
use App\Http\Controllers\DeliveryRunOrderController;
use App\Http\Controllers\DepartDeliveryRunController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryCountController;
use App\Http\Controllers\InventoryDocumentController;
use App\Http\Controllers\InventoryDocumentItemController;
use App\Http\Controllers\InventoryMovementController;
use App\Http\Controllers\InventoryStockController;
use App\Http\Controllers\MeasurementUnitController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderConversionSuggestionController;
use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\OrderItemQuoteController;
use App\Http\Controllers\PostInventoryCountController;
use App\Http\Controllers\PostInventoryDocumentController;
use App\Http\Controllers\PriceTierController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductConversionPreviewController;
use App\Http\Controllers\ProductPresentationController;
use App\Http\Controllers\ProductSupplierController;
use App\Http\Controllers\ReopenOrderController;
use App\Http\Controllers\RequeueDeliveryOrderController;
use App\Http\Controllers\RouteStopController;
use App\Http\Controllers\SalesRouteController;
use App\Http\Controllers\SettleDeliveryRunController;
use App\Http\Controllers\StartDeliveryPreparationController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UpdateDeliveryPreparationController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\VoidDeliveryPaymentController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'can:access-panel'])
    ->name('dashboard');

Route::middleware(['auth', 'can:manage-catalog'])->group(function (): void {
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

Route::middleware(['auth', 'can:manage-customers'])->group(function (): void {
    Route::resource('customers', CustomerController::class);
});

Route::get('/routes/create', [SalesRouteController::class, 'create'])
    ->middleware(['auth', 'can:manage-routes'])
    ->name('routes.create');

Route::middleware(['auth', 'can:view-routes'])->group(function (): void {
    Route::get('/routes', [SalesRouteController::class, 'index'])->name('routes.index');
    Route::get('/routes/{salesRoute}', [SalesRouteController::class, 'show'])->name('routes.show');
});

Route::middleware(['auth', 'can:manage-routes'])->group(function (): void {
    Route::resource('routes', SalesRouteController::class)
        ->parameters(['routes' => 'salesRoute'])
        ->only(['store', 'edit', 'update', 'destroy']);

    Route::scopeBindings()->group(function (): void {
        Route::post('/routes/{salesRoute}/stops', [RouteStopController::class, 'store'])
            ->name('routes.stops.store');
        Route::put('/routes/{salesRoute}/stops/{stop}', [RouteStopController::class, 'update'])
            ->name('routes.stops.update');
        Route::delete('/routes/{salesRoute}/stops/{stop}', [RouteStopController::class, 'destroy'])
            ->name('routes.stops.destroy');
    });
});

Route::get('/orders/create', [OrderController::class, 'create'])
    ->middleware(['auth', 'can:manage-orders'])
    ->name('orders.create');

Route::middleware(['auth', 'can:view-orders'])->group(function (): void {
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
});

Route::middleware(['auth', 'can:manage-orders'])->group(function (): void {
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::put('/orders/{order}', [OrderController::class, 'update'])->name('orders.update');
    Route::get('/orders/{order}/item-quote', OrderItemQuoteController::class)->name('orders.item-quote');
    Route::get('/orders/{order}/conversion-suggestions', OrderConversionSuggestionController::class)
        ->name('orders.conversion-suggestions');
    Route::post('/orders/{order}/confirm', ConfirmOrderController::class)->name('orders.confirm');

    Route::scopeBindings()->group(function (): void {
        Route::post('/orders/{order}/items', [OrderItemController::class, 'store'])->name('orders.items.store');
        Route::put('/orders/{order}/items/{orderItem}', [OrderItemController::class, 'update'])
            ->name('orders.items.update');
        Route::delete('/orders/{order}/items/{orderItem}', [OrderItemController::class, 'destroy'])
            ->name('orders.items.destroy');
    });
});

Route::middleware(['auth', 'can:view-orders'])->group(function (): void {
    Route::post('/orders/{order}/cancel', CancelOrderController::class)->name('orders.cancel');
    Route::post('/orders/{order}/reopen', ReopenOrderController::class)->name('orders.reopen');
});

Route::get('/delivery-runs/create', [DeliveryRunController::class, 'create'])
    ->middleware(['auth', 'can:access-panel', 'can:manage-deliveries'])
    ->name('delivery-runs.create');

Route::middleware(['auth', 'can:access-panel', 'can:view-deliveries'])->group(function (): void {
    Route::get('/delivery-runs', [DeliveryRunController::class, 'index'])->name('delivery-runs.index');
    Route::get('/delivery-runs/{deliveryRun}', [DeliveryRunController::class, 'show'])->name('delivery-runs.show');
    Route::post('/delivery-runs', [DeliveryRunController::class, 'store'])->name('delivery-runs.store');
    Route::get('/delivery-runs/{deliveryRun}/edit', [DeliveryRunController::class, 'edit'])->name('delivery-runs.edit');
    Route::put('/delivery-runs/{deliveryRun}', [DeliveryRunController::class, 'update'])->name('delivery-runs.update');
    Route::post('/delivery-runs/{deliveryRun}/preparation', StartDeliveryPreparationController::class)
        ->name('delivery-runs.preparation.start');
    Route::put('/delivery-runs/{deliveryRun}/preparation', UpdateDeliveryPreparationController::class)
        ->name('delivery-runs.preparation.update');
    Route::post('/delivery-runs/{deliveryRun}/load', ConfirmDeliveryLoadController::class)
        ->name('delivery-runs.load');
    Route::post('/delivery-runs/{deliveryRun}/depart', DepartDeliveryRunController::class)
        ->name('delivery-runs.depart');
    Route::post('/delivery-runs/{deliveryRun}/settle', SettleDeliveryRunController::class)
        ->name('delivery-runs.settle');
    Route::post('/delivery-runs/{deliveryRun}/cancel', CancelDeliveryRunController::class)
        ->name('delivery-runs.cancel');

    Route::scopeBindings()->group(function (): void {
        Route::post('/delivery-runs/{deliveryRun}/orders', [DeliveryRunOrderController::class, 'store'])
            ->name('delivery-runs.orders.store');
        Route::delete('/delivery-runs/{deliveryRun}/orders/{runOrder}', [DeliveryRunOrderController::class, 'destroy'])
            ->name('delivery-runs.orders.destroy');
        Route::put('/delivery-runs/{deliveryRun}/orders/{runOrder}/outcome', CompleteDeliveryStopController::class)
            ->name('delivery-runs.orders.outcome');
        Route::post('/delivery-runs/{deliveryRun}/orders/{runOrder}/payments', [DeliveryPaymentController::class, 'store'])
            ->name('delivery-runs.orders.payments.store');
        Route::post('/delivery-runs/{deliveryRun}/orders/{runOrder}/payments/{payment}/void', VoidDeliveryPaymentController::class)
            ->name('delivery-runs.orders.payments.void');
        Route::post('/delivery-runs/{deliveryRun}/orders/{runOrder}/requeue', RequeueDeliveryOrderController::class)
            ->name('delivery-runs.orders.requeue');
    });
});

Route::middleware(['auth', 'can:access-panel', 'can:manage-vehicles'])->group(function (): void {
    Route::resource('vehicles', VehicleController::class)->except(['show', 'destroy']);
});

Route::get('/inventory', InventoryController::class)
    ->middleware(['auth', 'can:view-inventory'])
    ->name('inventory.index');

Route::middleware(['auth', 'can:operate-inventory'])->group(function (): void {
    Route::get('/inventory/movements', [InventoryMovementController::class, 'index'])
        ->name('inventory-movements.index');

    Route::get('/inventory/documents/create', [InventoryDocumentController::class, 'create'])
        ->name('inventory-documents.create');
    Route::get('/inventory/documents', [InventoryDocumentController::class, 'index'])
        ->name('inventory-documents.index');
    Route::post('/inventory/documents', [InventoryDocumentController::class, 'store'])
        ->name('inventory-documents.store');
    Route::get('/inventory/documents/{inventoryDocument}', [InventoryDocumentController::class, 'show'])
        ->name('inventory-documents.show');
    Route::get('/inventory/documents/{inventoryDocument}/edit', [InventoryDocumentController::class, 'edit'])
        ->name('inventory-documents.edit');
    Route::put('/inventory/documents/{inventoryDocument}', [InventoryDocumentController::class, 'update'])
        ->name('inventory-documents.update');
    Route::post('/inventory/documents/{inventoryDocument}/post', PostInventoryDocumentController::class)
        ->name('inventory-documents.post');
    Route::post('/inventory/documents/{inventoryDocument}/cancel', CancelInventoryDocumentController::class)
        ->name('inventory-documents.cancel');

    Route::scopeBindings()->group(function (): void {
        Route::post('/inventory/documents/{inventoryDocument}/items', [InventoryDocumentItemController::class, 'store'])
            ->name('inventory-documents.items.store');
        Route::put('/inventory/documents/{inventoryDocument}/items/{inventoryDocumentItem}', [InventoryDocumentItemController::class, 'update'])
            ->name('inventory-documents.items.update');
        Route::delete('/inventory/documents/{inventoryDocument}/items/{inventoryDocumentItem}', [InventoryDocumentItemController::class, 'destroy'])
            ->name('inventory-documents.items.destroy');
    });

    Route::get('/inventory/counts/create', [InventoryCountController::class, 'create'])
        ->name('inventory-counts.create');
    Route::get('/inventory/counts', [InventoryCountController::class, 'index'])
        ->name('inventory-counts.index');
    Route::post('/inventory/counts', [InventoryCountController::class, 'store'])
        ->name('inventory-counts.store');
    Route::get('/inventory/counts/{inventoryCount}', [InventoryCountController::class, 'show'])
        ->name('inventory-counts.show');
    Route::put('/inventory/counts/{inventoryCount}', [InventoryCountController::class, 'update'])
        ->name('inventory-counts.update');
    Route::post('/inventory/counts/{inventoryCount}/post', PostInventoryCountController::class)
        ->name('inventory-counts.post');
    Route::post('/inventory/counts/{inventoryCount}/cancel', CancelInventoryCountController::class)
        ->name('inventory-counts.cancel');
});

Route::middleware(['auth', 'can:configure-inventory'])->group(function (): void {
    Route::resource('warehouses', WarehouseController::class)->except(['show', 'destroy']);
    Route::put('/inventory/stocks/{inventoryStock}', [InventoryStockController::class, 'update'])
        ->name('inventory-stocks.update');
});

Route::middleware(['auth', 'can:manage-users'])->group(function (): void {
    Route::resource('users', UserController::class)->except(['show', 'destroy']);
});
