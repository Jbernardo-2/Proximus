<?php

namespace App\Http\Controllers;

use App\Models\ProductPresentation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductPresentationLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless(
            $user->canManageCatalog() || $user->canManageOrders() || $user->canOperateInventory(),
            403,
        );

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'mode' => ['nullable', Rule::in(['active', 'sellable', 'purchasable'])],
            'warehouse_id' => ['nullable', 'ulid', 'exists:warehouses,id'],
        ]);
        $search = trim((string) ($validated['q'] ?? ''));
        $mode = $validated['mode'] ?? 'active';
        $warehouseId = $validated['warehouse_id'] ?? null;

        $presentations = ProductPresentation::query()
            ->active()
            ->when($mode === 'sellable', fn ($query) => $query->where('is_sellable', true))
            ->when($mode === 'purchasable', fn ($query) => $query->where('is_purchasable', true))
            ->whereHas('product', fn ($query) => $query->active())
            ->with([
                'priceTiers' => fn ($query) => $query
                    ->active()
                    ->where(fn ($dates) => $dates->whereNull('starts_at')->orWhere('starts_at', '<=', today()))
                    ->where(fn ($dates) => $dates->whereNull('ends_at')->orWhere('ends_at', '>=', today()))
                    ->orderBy('min_quantity'),
                'product.category:id,name',
                'product.brand:id,name',
                'product.baseUnit:id,name,symbol',
                'product.inventoryStocks' => fn ($query) => $query
                    ->when($warehouseId !== null, fn ($stocks) => $stocks->where('warehouse_id', $warehouseId)),
            ])
            ->when($search !== '', fn ($query) => $query->where(function ($presentations) use ($search): void {
                $presentations->where('barcode', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($products) => $products
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('category', fn ($categories) => $categories->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('brand', fn ($brands) => $brands->where('name', 'like', "%{$search}%")));
            }))
            ->when($search !== '', fn ($query) => $query->orderByRaw(
                'case when barcode = ? then 0 when exists (select 1 from products where products.id = product_presentations.product_id and products.sku = ?) then 1 else 2 end',
                [$search, $search],
            ))
            ->orderBy('product_id')
            ->orderByDesc('conversion_factor')
            ->orderBy('name')
            ->paginate(20);

        return response()->json([
            'data' => $presentations->getCollection()->map(function (ProductPresentation $presentation) use ($warehouseId): array {
                $product = $presentation->product;
                $stock = $warehouseId === null ? null : $product->inventoryStocks->firstWhere('warehouse_id', $warehouseId);

                return [
                    'id' => $presentation->id,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'description' => $product->description,
                    'image_url' => $product->image_path ? Storage::disk('public')->url($product->image_path) : null,
                    'category' => $product->category?->name,
                    'brand' => $product->brand?->name,
                    'base_unit' => $product->baseUnit?->symbol,
                    'allows_decimal' => $product->allows_decimal,
                    'tracks_lots' => $product->tracks_lots,
                    'tracks_expiration' => $product->tracks_expiration,
                    'presentation' => $presentation->name,
                    'barcode' => $presentation->barcode,
                    'conversion_factor' => $presentation->conversion_factor,
                    'sale_price' => $presentation->sale_price,
                    'is_sellable' => $presentation->is_sellable,
                    'is_purchasable' => $presentation->is_purchasable,
                    'stock' => $stock === null ? null : [
                        'on_hand' => $stock->quantity_on_hand,
                        'reserved' => $stock->quantity_reserved,
                        'available' => $stock->availableQuantity(),
                    ],
                    'price_tiers' => $presentation->priceTiers->map(fn ($tier): array => [
                        'min_quantity' => $tier->min_quantity,
                        'max_quantity' => $tier->max_quantity,
                        'unit_price' => $tier->unit_price,
                    ])->values(),
                ];
            })->values(),
            'meta' => [
                'current_page' => $presentations->currentPage(),
                'last_page' => $presentations->lastPage(),
                'has_more' => $presentations->hasMorePages(),
                'total' => $presentations->total(),
            ],
        ]);
    }
}
