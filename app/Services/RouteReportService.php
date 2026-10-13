<?php

namespace App\Services;

use App\Models\CompanyProfile;
use App\Models\DeliveryRun;
use App\Models\SalesRoute;
use App\PaymentTerm;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RouteReportService
{
    /**
     * @return array<string, mixed>
     */
    public function data(DeliveryRun $deliveryRun, SalesRoute $salesRoute): array
    {
        $deliveryRun->load([
            'warehouse',
            'driver',
            'vehicle',
            'runOrders' => fn ($query) => $query
                ->whereHas('order', fn ($orders) => $orders->where('sales_route_id', $salesRoute->id))
                ->with([
                    'order.customer',
                    'order.salesperson',
                    'items' => fn ($items) => $items
                        ->orderBy('product_name')
                        ->orderBy('presentation_name')
                        ->orderBy('id'),
                ])
                ->orderBy('visit_order')
                ->orderBy('id'),
        ]);
        $salesRoute->loadMissing('salesperson');

        $runOrders = $deliveryRun->runOrders;
        $consolidated = $runOrders
            ->flatMap->items
            ->groupBy(fn ($item): string => $item->product_id.'|'.$item->product_presentation_id)
            ->map(function ($items): array {
                $first = $items->first();

                return [
                    'product_sku' => $first->product_sku,
                    'product_name' => $first->product_name,
                    'presentation_name' => $first->presentation_name,
                    'base_unit_symbol' => $first->base_unit_symbol,
                    'requested_quantity' => $items->reduce(
                        fn (string $total, $item): string => bcadd($total, (string) $item->requested_quantity, 6),
                        '0.000000',
                    ),
                    'requested_base_quantity' => $items->reduce(
                        fn (string $total, $item): string => bcadd($total, (string) $item->requested_base_quantity, 6),
                        '0.000000',
                    ),
                ];
            })
            ->sortBy(fn (array $item): string => $item['product_name'].'|'.$item['presentation_name'])
            ->values();
        $companyProfile = CompanyProfile::query()->findOrFail(CompanyProfile::SINGLETON_ID);

        return [
            'companyProfile' => $companyProfile,
            'companyLogoDataUri' => $this->companyLogoDataUri($companyProfile),
            'deliveryRun' => $deliveryRun,
            'salesRoute' => $salesRoute,
            'runOrders' => $runOrders,
            'consolidated' => $consolidated,
            'metrics' => [
                'orders' => $runOrders->count(),
                'customers' => $runOrders->pluck('order.customer_id')->filter()->unique()->count(),
                'requested_total' => $runOrders->reduce(
                    fn (string $total, $runOrder): string => bcadd($total, (string) $runOrder->requested_total, 4),
                    '0.0000',
                ),
                'cash_orders' => $runOrders->filter(
                    fn ($runOrder): bool => $runOrder->order->payment_term === PaymentTerm::Cash,
                )->count(),
                'credit_orders' => $runOrders->filter(
                    fn ($runOrder): bool => $runOrder->order->payment_term === PaymentTerm::Credit,
                )->count(),
            ],
            'generatedAt' => now(),
        ];
    }

    public function filename(DeliveryRun $deliveryRun, SalesRoute $salesRoute): string
    {
        $route = Str::slug($salesRoute->code ?: $salesRoute->name) ?: 'ruta';

        return "ruta-{$route}-{$deliveryRun->scheduled_date->format('Y-m-d')}.pdf";
    }

    private function companyLogoDataUri(CompanyProfile $companyProfile): ?string
    {
        if ($companyProfile->logo_path === null || ! Storage::disk('public')->exists($companyProfile->logo_path)) {
            return null;
        }

        $mimeType = Storage::disk('public')->mimeType($companyProfile->logo_path);
        $contents = Storage::disk('public')->get($companyProfile->logo_path);

        return 'data:'.$mimeType.';base64,'.base64_encode($contents);
    }
}
