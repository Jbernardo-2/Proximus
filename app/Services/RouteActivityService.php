<?php

namespace App\Services;

use App\CustomerAccountEntryStatus;
use App\CustomerAccountEntryType;
use App\CustomerVisitType;
use App\Models\CustomerAccountEntry;
use App\Models\CustomerVisit;
use App\Models\Order;
use App\Models\RouteStop;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RouteActivityService
{
    public function query(User $user): Builder
    {
        return RouteStop::query()
            ->where('is_active', true)
            ->where('visit_day', now()->dayOfWeekIso)
            ->whereHas(
                'salesRoute',
                fn (Builder $routes): Builder => $routes
                    ->active()
                    ->where('salesperson_id', $user->id),
            )
            ->with(['salesRoute:id,code,name,salesperson_id', 'customer']);
    }

    /**
     * @param  Collection<int, RouteStop>  $stops
     * @return list<array<string, mixed>>
     */
    public function map(Collection $stops, User $user): array
    {
        $stopIds = $stops->pluck('id');
        $customerIds = $stops->pluck('customer_id');
        $start = today()->startOfDay();
        $end = today()->endOfDay();
        $orders = Order::query()
            ->whereIn('route_stop_id', $stopIds)
            ->where('salesperson_id', $user->id)
            ->whereDate('order_date', today())
            ->latest('created_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('route_stop_id');
        $visits = CustomerVisit::query()
            ->whereIn('customer_id', $customerIds)
            ->where('user_id', $user->id)
            ->whereBetween('occurred_at', [$start, $end])
            ->latest('occurred_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('customer_id');
        $payments = CustomerAccountEntry::query()
            ->whereIn('customer_id', $customerIds)
            ->where('type', CustomerAccountEntryType::CustomerPayment)
            ->where('status', CustomerAccountEntryStatus::Active)
            ->where('created_by', $user->id)
            ->whereBetween('occurred_at', [$start, $end])
            ->get()
            ->groupBy('customer_id');

        return $stops->map(function (RouteStop $stop) use ($orders, $visits, $payments): array {
            $stopOrders = $orders->get($stop->id, collect());
            $customerVisits = $visits->get($stop->customer_id, collect());
            $customerPayments = $payments->get($stop->customer_id, collect());
            /** @var Order|null $order */
            $order = $stopOrders->first();
            /** @var CustomerVisit|null $visit */
            $visit = $customerVisits->first();
            $noSale = $customerVisits->first(fn (CustomerVisit $candidate): bool => $candidate->type === CustomerVisitType::NoSale);

            return [
                'id' => $stop->id,
                'date' => today()->toDateString(),
                'route_stop_id' => $stop->id,
                'visit_day' => $stop->visit_day->value,
                'visit_order' => $stop->visit_order,
                'notes' => $stop->notes,
                // Sale outcome of the stop; payments are reported apart because both can happen the same day.
                'status' => match (true) {
                    $order !== null => 'order_taken',
                    $noSale !== null => CustomerVisitType::NoSale->value,
                    $visit !== null => 'visited',
                    default => 'pending',
                },
                'has_order' => $order !== null,
                'has_no_sale' => $noSale !== null,
                'has_payment' => $customerPayments->isNotEmpty(),
                'orders_count' => $stopOrders->count(),
                'orders_total' => $stopOrders->reduce(fn (string $total, Order $item): string => bcadd($total, (string) $item->total, 4), '0.0000'),
                'payments_count' => $customerPayments->count(),
                'payments_total' => $customerPayments->reduce(fn (string $total, CustomerAccountEntry $entry): string => bcadd($total, (string) $entry->credit_amount, 4), '0.0000'),
                'activity_at' => $order?->created_at?->toIso8601String() ?? $visit?->occurred_at?->toIso8601String(),
                'route' => [
                    'id' => $stop->salesRoute->id,
                    'code' => $stop->salesRoute->code,
                    'name' => $stop->salesRoute->name,
                ],
                'customer' => [
                    'id' => $stop->customer->id,
                    'code' => $stop->customer->code,
                    'business_name' => $stop->customer->business_name,
                    'contact_name' => $stop->customer->contact_name,
                    'phone' => $stop->customer->phone,
                    'whatsapp' => $stop->customer->whatsapp,
                    'address' => $stop->customer->address,
                    'reference' => $stop->customer->reference,
                    'latitude' => $stop->customer->latitude,
                    'longitude' => $stop->customer->longitude,
                ],
                'order' => $order === null ? null : [
                    'id' => $order->id,
                    'client_reference' => $order->client_reference,
                    'order_number' => $order->order_number,
                    'status' => $order->status->value,
                    'status_label' => $order->status->label(),
                    'payment_term' => $order->payment_term->value,
                    'total' => $order->total,
                    'created_at' => $order->created_at?->toIso8601String(),
                ],
                'visit' => $visit === null ? null : [
                    'id' => $visit->id,
                    'client_reference' => $visit->client_reference,
                    'type' => $visit->type->value,
                    'type_label' => $visit->type->label(),
                    'reason' => $visit->reason?->value,
                    'notes' => $visit->notes,
                    'location_status' => $visit->location_status->value,
                    'distance_meters' => $visit->distance_meters,
                    'occurred_at' => $visit->occurred_at?->toIso8601String(),
                ],
            ];
        })->sortBy('visit_order')->values()->all();
    }
}
