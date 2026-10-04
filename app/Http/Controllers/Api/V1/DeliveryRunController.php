<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateDeliveryRunAction;
use App\Actions\UpdateDeliveryRunAction;
use App\DeliveryRunStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeliveryRunRequest;
use App\Http\Requests\UpdateDeliveryRunRequest;
use App\Http\Resources\Api\V1\DeliveryRunResource;
use App\Models\DeliveryRun;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class DeliveryRunController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', DeliveryRun::class);
        /** @var User $user */
        $user = $request->user();
        $status = $request->string('status')->toString();

        return DeliveryRunResource::collection(DeliveryRun::query()
            ->visibleTo($user)
            ->with(['warehouse', 'driver', 'vehicle'])
            ->withCount('runOrders')
            ->when(DeliveryRunStatus::tryFrom($status) !== null, fn ($query) => $query->where('status', $status))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('scheduled_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('scheduled_date', '<=', $request->input('date_to')))
            ->orderByDesc('scheduled_date')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100)));
    }

    public function store(
        StoreDeliveryRunRequest $request,
        CreateDeliveryRunAction $createDeliveryRun,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $deliveryRun = $createDeliveryRun->handle($request->validated(), $user);

        return (new DeliveryRunResource($this->loadRun($deliveryRun)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(DeliveryRun $deliveryRun): DeliveryRunResource
    {
        Gate::authorize('view', $deliveryRun);

        return new DeliveryRunResource($this->loadRun($deliveryRun));
    }

    public function update(
        UpdateDeliveryRunRequest $request,
        DeliveryRun $deliveryRun,
        UpdateDeliveryRunAction $updateDeliveryRun,
    ): DeliveryRunResource {
        return new DeliveryRunResource($this->loadRun(
            $updateDeliveryRun->handle($deliveryRun, $request->validated()),
        ));
    }

    private function loadRun(DeliveryRun $deliveryRun): DeliveryRun
    {
        return $deliveryRun->load([
            'warehouse',
            'driver',
            'vehicle',
            'runOrders' => fn ($query) => $query
                ->with([
                    'order',
                    'items',
                    'payments.receivedBy',
                    'payments.voidedBy',
                    'completedBy',
                ])
                ->orderBy('visit_order')
                ->orderBy('id'),
            'statusHistory' => fn ($query) => $query->with('changedBy')->latest()->orderByDesc('id'),
        ])->loadCount('runOrders');
    }
}
