<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\User;
use App\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless(
            $user->canManageCustomers() || $user->canManageRoutes() || $user->canManageOrders(),
            403,
        );

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);
        $search = trim((string) ($validated['q'] ?? ''));
        $preventista = $user->role === UserRole::Preventista;
        $routeScope = fn ($query) => $query
            ->where('route_stops.is_active', true)
            ->whereHas('salesRoute', fn ($routes) => $routes
                ->active()
                ->when($preventista, fn ($assigned) => $assigned->where('salesperson_id', $user->id)));

        $customers = Customer::query()
            ->active()
            ->when($preventista, fn ($query) => $query->whereHas('routeStops', $routeScope))
            ->with(['routeStops' => fn ($query) => $routeScope($query)
                ->with('salesRoute:id,code,name,salesperson_id')
                ->orderBy('visit_day')
                ->orderBy('visit_order')])
            ->when($search !== '', fn ($query) => $query->where(function ($customers) use ($search): void {
                $customers->where('business_name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('whatsapp', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            }))
            ->when($search !== '', fn ($query) => $query->orderByRaw(
                'case when code = ? then 0 when business_name = ? then 1 else 2 end',
                [$search, $search],
            ))
            ->orderBy('business_name')
            ->orderBy('id')
            ->paginate(20);

        return response()->json([
            'data' => $customers->getCollection()->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'code' => $customer->code,
                'name' => $customer->business_name,
                'business_type' => $customer->business_type,
                'contact_name' => $customer->contact_name,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'latitude' => $customer->latitude,
                'longitude' => $customer->longitude,
                'route_stops' => $customer->routeStops->map(fn ($stop): array => [
                    'id' => $stop->id,
                    'route_id' => $stop->sales_route_id,
                    'route_name' => $stop->salesRoute?->name,
                    'route_code' => $stop->salesRoute?->code,
                    'salesperson_id' => $stop->salesRoute?->salesperson_id,
                    'visit_day' => $stop->visit_day->value,
                    'visit_day_label' => $stop->visit_day->label(),
                    'visit_order' => $stop->visit_order,
                ])->values(),
            ])->values(),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'has_more' => $customers->hasMorePages(),
                'total' => $customers->total(),
            ],
        ]);
    }
}
