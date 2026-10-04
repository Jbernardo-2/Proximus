<?php

namespace App\Http\Controllers;

use App\Actions\CreateInventoryDocumentAction;
use App\Actions\UpdateInventoryDocumentAction;
use App\Http\Requests\StoreInventoryDocumentRequest;
use App\Http\Requests\UpdateInventoryDocumentRequest;
use App\InventoryDocumentStatus;
use App\InventoryDocumentType;
use App\Models\InventoryDocument;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InventoryDocumentController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', InventoryDocument::class);
        $search = $request->string('search')->trim()->toString();
        $type = $request->string('type')->toString();
        $status = $request->string('status')->toString();

        return view('inventory.documents.index', [
            'documents' => InventoryDocument::query()
                ->with(['warehouse', 'supplier', 'creator'])
                ->withCount('items')
                ->when($request->filled('warehouse_id'), fn ($query) => $query->where('warehouse_id', $request->input('warehouse_id')))
                ->when(InventoryDocumentType::tryFrom($type) !== null, fn ($query) => $query->where('type', $type))
                ->when(InventoryDocumentStatus::tryFrom($status) !== null, fn ($query) => $query->where('status', $status))
                ->when($search !== '', fn ($query) => $query->where(function ($builder) use ($search): void {
                    $builder->where('document_number', 'like', "%{$search}%")
                        ->orWhere('external_reference', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn ($suppliers) => $suppliers->withTrashed()->where('name', 'like', "%{$search}%"));
                }))
                ->latest('occurred_on')
                ->latest('created_at')
                ->paginate(25)
                ->withQueryString(),
            'warehouses' => Warehouse::query()->orderByDesc('is_default')->orderBy('name')->get(),
            'types' => InventoryDocumentType::cases(),
            'statuses' => InventoryDocumentStatus::cases(),
            'search' => $search,
            'selectedType' => $type,
            'selectedStatus' => $status,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', InventoryDocument::class);

        return view('inventory.documents.form', [
            'inventoryDocument' => new InventoryDocument,
            ...$this->formOptions(),
        ]);
    }

    public function store(
        StoreInventoryDocumentRequest $request,
        CreateInventoryDocumentAction $createDocument,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $document = $createDocument->handle($request->validated(), $user);

        return redirect()->route('inventory-documents.show', $document)
            ->with('success', 'Movimiento creado como borrador. Agrega los productos antes de aplicarlo.');
    }

    public function show(InventoryDocument $inventoryDocument): View
    {
        Gate::authorize('view', $inventoryDocument);
        $canUpdate = Gate::allows('update', $inventoryDocument);
        $inventoryDocument->load([
            'warehouse',
            'supplier',
            'creator',
            'postedBy',
            'cancelledBy',
            'items' => fn ($query) => $query->with(['product', 'presentation'])->orderBy('product_name')->orderBy('id'),
        ]);

        return view('inventory.documents.show', [
            'document' => $inventoryDocument,
            'canUpdate' => $canUpdate,
            'products' => $canUpdate ? Product::query()
                ->active()
                ->with([
                    'baseUnit',
                    'presentations' => fn ($query) => $query->active()->orderByDesc('conversion_factor')->orderBy('name'),
                ])
                ->whereHas('presentations', fn ($query) => $query->active())
                ->orderBy('name')
                ->get() : collect(),
        ]);
    }

    public function edit(InventoryDocument $inventoryDocument): View
    {
        Gate::authorize('update', $inventoryDocument);

        return view('inventory.documents.form', [
            'inventoryDocument' => $inventoryDocument,
            ...$this->formOptions(),
        ]);
    }

    public function update(
        UpdateInventoryDocumentRequest $request,
        InventoryDocument $inventoryDocument,
        UpdateInventoryDocumentAction $updateDocument,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $updateDocument->handle($inventoryDocument, $request->validated(), $user);

        return redirect()->route('inventory-documents.show', $inventoryDocument)
            ->with('success', 'Encabezado del movimiento actualizado.');
    }

    private function formOptions(): array
    {
        return [
            'warehouses' => Warehouse::query()->active()->orderByDesc('is_default')->orderBy('name')->get(),
            'suppliers' => Supplier::query()->active()->orderBy('name')->get(),
            'types' => InventoryDocumentType::cases(),
        ];
    }
}
