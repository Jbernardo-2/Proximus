<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CancelInventoryCountAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelInventoryRequest;
use App\Http\Resources\Api\V1\InventoryCountResource;
use App\Models\InventoryCount;
use App\Models\User;

class CancelInventoryCountController extends Controller
{
    public function __invoke(
        CancelInventoryRequest $request,
        InventoryCount $inventoryCount,
        CancelInventoryCountAction $cancelCount,
    ): InventoryCountResource {
        /** @var User $user */
        $user = $request->user();
        $count = $cancelCount->handle($inventoryCount, $user, $request->validated('reason'));

        return new InventoryCountResource($count->load(['warehouse', 'creator', 'cancelledBy', 'items'])->loadCount('items'));
    }
}
