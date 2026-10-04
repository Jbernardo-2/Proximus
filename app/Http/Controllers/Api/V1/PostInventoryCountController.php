<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\PostInventoryCountAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\PostInventoryCountRequest;
use App\Http\Resources\Api\V1\InventoryCountResource;
use App\Models\InventoryCount;
use App\Models\User;

class PostInventoryCountController extends Controller
{
    public function __invoke(
        PostInventoryCountRequest $request,
        InventoryCount $inventoryCount,
        PostInventoryCountAction $postCount,
    ): InventoryCountResource {
        /** @var User $user */
        $user = $request->user();
        $count = $postCount->handle($inventoryCount, $user);

        return new InventoryCountResource($count->load(['warehouse', 'creator', 'postedBy', 'items'])->loadCount('items'));
    }
}
