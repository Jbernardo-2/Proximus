<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateInventoryCountAction;
use App\Actions\SaveInventoryCountItemsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventoryCountRequest;
use App\Http\Requests\UpdateInventoryCountItemsRequest;
use App\Http\Resources\Api\V1\InventoryCountResource;
use App\InventoryCountStatus;
use App\Models\InventoryCount;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class InventoryCountController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', InventoryCount::class);
        $status = $request->string('status')->toString();

        return InventoryCountResource::collection(InventoryCount::query()
            ->with(['warehouse', 'creator'])
            ->withCount([
                'items',
                'items as counted_items_count' => fn ($query) => $query->whereNotNull('counted_quantity'),
            ])
            ->when($request->filled('warehouse_id'), fn ($query) => $query->where('warehouse_id', $request->input('warehouse_id')))
            ->when(InventoryCountStatus::tryFrom($status) !== null, fn ($query) => $query->where('status', $status))
            ->latest('counted_on')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100)));
    }

    public function store(
        StoreInventoryCountRequest $request,
        CreateInventoryCountAction $createCount,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $count = $createCount->handle($request->validated(), $user);

        return (new InventoryCountResource($this->loadCount($count)))->response()->setStatusCode(201);
    }

    public function show(InventoryCount $inventoryCount): InventoryCountResource
    {
        Gate::authorize('view', $inventoryCount);

        return new InventoryCountResource($this->loadCount($inventoryCount));
    }

    public function update(
        UpdateInventoryCountItemsRequest $request,
        InventoryCount $inventoryCount,
        SaveInventoryCountItemsAction $saveItems,
    ): InventoryCountResource {
        return new InventoryCountResource($this->loadCount(
            $saveItems->handle($inventoryCount, $request->validated('items')),
        ));
    }

    private function loadCount(InventoryCount $count): InventoryCount
    {
        return $count->load([
            'warehouse',
            'creator',
            'postedBy',
            'cancelledBy',
            'items' => fn ($query) => $query->orderBy('product_name')->orderBy('id'),
        ])->loadCount([
            'items',
            'items as counted_items_count' => fn ($query) => $query->whereNotNull('counted_quantity'),
        ]);
    }
}
