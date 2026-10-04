<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateInventoryDocumentAction;
use App\Actions\UpdateInventoryDocumentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventoryDocumentRequest;
use App\Http\Requests\UpdateInventoryDocumentRequest;
use App\Http\Resources\Api\V1\InventoryDocumentResource;
use App\InventoryDocumentStatus;
use App\InventoryDocumentType;
use App\Models\InventoryDocument;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class InventoryDocumentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', InventoryDocument::class);
        $type = $request->string('type')->toString();
        $status = $request->string('status')->toString();

        return InventoryDocumentResource::collection(InventoryDocument::query()
            ->with(['warehouse', 'supplier', 'creator'])
            ->withCount('items')
            ->when($request->filled('warehouse_id'), fn ($query) => $query->where('warehouse_id', $request->input('warehouse_id')))
            ->when(InventoryDocumentType::tryFrom($type) !== null, fn ($query) => $query->where('type', $type))
            ->when(InventoryDocumentStatus::tryFrom($status) !== null, fn ($query) => $query->where('status', $status))
            ->latest('occurred_on')
            ->latest('created_at')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100)));
    }

    public function store(
        StoreInventoryDocumentRequest $request,
        CreateInventoryDocumentAction $createDocument,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $document = $createDocument->handle($request->validated(), $user);

        return (new InventoryDocumentResource($this->loadDocument($document)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(InventoryDocument $inventoryDocument): InventoryDocumentResource
    {
        Gate::authorize('view', $inventoryDocument);

        return new InventoryDocumentResource($this->loadDocument($inventoryDocument));
    }

    public function update(
        UpdateInventoryDocumentRequest $request,
        InventoryDocument $inventoryDocument,
        UpdateInventoryDocumentAction $updateDocument,
    ): InventoryDocumentResource {
        /** @var User $user */
        $user = $request->user();

        return new InventoryDocumentResource($this->loadDocument(
            $updateDocument->handle($inventoryDocument, $request->validated(), $user),
        ));
    }

    private function loadDocument(InventoryDocument $document): InventoryDocument
    {
        return $document->load([
            'warehouse',
            'supplier',
            'creator',
            'postedBy',
            'cancelledBy',
            'items' => fn ($query) => $query->orderBy('product_name')->orderBy('id'),
        ])->loadCount('items');
    }
}
