<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\RouteReportController as WebRouteReportController;
use App\Http\Requests\RouteReportIndexRequest;
use App\Models\DeliveryRun;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Route reports available to the user. The PDF comes from the inherited download action.
 */
class RouteReportController extends WebRouteReportController
{
    public function list(RouteReportIndexRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        [$runs, $reportsByRun] = $this->runReports($user, $request->validated());

        return response()->json([
            'data' => collect($runs->items())->map(fn (DeliveryRun $run): array => [
                'delivery_run_id' => $run->id,
                'run_number' => $run->run_number,
                'scheduled_date' => $run->scheduled_date?->toDateString(),
                'status' => $run->status->value,
                'status_label' => $run->status->label(),
                'driver_name' => $run->driver_name,
                'vehicle' => $run->vehicle?->code,
                'routes' => $reportsByRun->get($run->id, collect())->map(fn (array $report): array => [
                    'sales_route_id' => $report['route']->id,
                    'code' => $report['route']->code,
                    'name' => $report['route']->name,
                    'salesperson_name' => $report['route']->salesperson?->name,
                    'orders_count' => $report['orders_count'],
                    'download_url' => route('api.v1.route-reports.download', [$run, $report['route']]),
                ])->values()->all(),
            ])->all(),
            'meta' => [
                'current_page' => $runs->currentPage(),
                'last_page' => $runs->lastPage(),
                'total' => $runs->total(),
            ],
        ]);
    }
}
