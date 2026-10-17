<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateInventoryDocumentAction;
use App\Actions\PostInventoryDocumentAction;
use App\Actions\RecordProductCountAction;
use App\Actions\SaveInventoryDocumentItemAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventoryReceiptRequest;
use App\Http\Requests\StoreProductCountRequest;
use App\Http\Resources\Api\V1\InventoryCountResource;
use App\Http\Resources\Api\V1\InventoryDocumentResource;
use App\Models\User;
use App\InventoryCountStatus;
use App\Models\InventoryCount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;

/** What the warehouse phone records in one request each: a product count and a whole receipt. */
class WarehouseFieldController extends Controller
{
    /** The latest single-product counts: the keeper's own, or everyone's for a supervisor. */
    public function counts(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', InventoryCount::class);
        /** @var User $user */
        $user = $request->user();

        return InventoryCountResource::collection(InventoryCount::query()
            ->with(['warehouse', 'items', 'creator'])
            ->where('status', InventoryCountStatus::Posted->value)
            ->has('items', '=', 1)
            ->when(! $user->canAdjustInventory(), fn ($query) => $query->where('created_by', $user->id))
            ->latest('posted_at')
            ->limit(50)
            ->get());
    }

    public function count(StoreProductCountRequest $request, RecordProductCountAction $recordCount): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return (new InventoryCountResource($recordCount->handle($request->validated(), $user)))
            ->response()
            ->setStatusCode(201);
    }

    public function receipt(
        StoreInventoryReceiptRequest $request,
        CreateInventoryDocumentAction $createDocument,
        SaveInventoryDocumentItemAction $saveItem,
        PostInventoryDocumentAction $postDocument,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        $document = DB::transaction(function () use ($data, $user, $createDocument, $saveItem, $postDocument) {
            $document = $createDocument->handle(collect($data)->except('items')->all(), $user);

            foreach ($data['items'] as $item) {
                $saveItem->handle($document, $item);
            }

            return $postDocument->handle($document->refresh(), $user);
        });

        return (new InventoryDocumentResource($document->load(['warehouse', 'supplier', 'items'])))
            ->response()
            ->setStatusCode(201);
    }
}
