<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();
        $businessType = $request->string('business_type')->trim()->toString();
        $customers = Customer::query()
            ->withCount(['routeStops' => fn ($query) => $query->visibleTo($user)])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder->where('business_name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('contact_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('whatsapp', 'like', "%{$search}%");
                });
            })
            ->when($businessType !== '', fn ($query) => $query->where('business_type', $businessType))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('is_active', $status === 'active'))
            ->orderByDesc('is_active')
            ->orderBy('business_name')
            ->orderBy('id')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return CustomerResource::collection($customers);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = Customer::query()->create($request->validated());

        return (new CustomerResource($customer->loadCount('routeStops')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Customer $customer): CustomerResource
    {
        /** @var User $user */
        $user = $request->user();

        return new CustomerResource($customer->load([
            'routeStops' => fn ($query) => $query
                ->with('salesRoute')
                ->visibleTo($user)
                ->orderBy('visit_day')
                ->orderBy('visit_order')
                ->orderBy('id'),
        ])->loadCount(['routeStops' => fn ($query) => $query->visibleTo($user)]));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): CustomerResource
    {
        $customer->update($request->validated());

        return new CustomerResource($customer->refresh()->loadCount([
            'routeStops' => fn ($query) => $query->visibleTo($request->user()),
        ]));
    }

    public function destroy(Customer $customer): JsonResponse
    {
        if ($customer->routeStops()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar un cliente asignado a rutas.',
            ], 422);
        }

        $customer->delete();

        return response()->json(null, 204);
    }
}
