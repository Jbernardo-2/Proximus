<?php

namespace App\Services\Reports;

use Illuminate\Support\Collection;

class ReportRegistry
{
    private const array REPORTS = [
        ExecutiveSummaryReport::class,
        SalesDetailReport::class,
        SalesByProductReport::class,
        SalesByCustomerReport::class,
        SalesByRouteReport::class,
        SalesBySalespersonReport::class,
        FulfillmentReport::class,
        RouteResultsReport::class,
        DriverPerformanceReport::class,
        LoadReconciliationReport::class,
        PaymentsReport::class,
        RunSettlementsReport::class,
        ReceivablesAgingReport::class,
        CreditUsageReport::class,
        CreditExceptionsReport::class,
        FieldVisitsReport::class,
        SyncOperationsReport::class,
    ];

    /** @return Collection<int, Report> */
    public function all(): Collection
    {
        return collect(self::REPORTS)->map(fn (string $report): Report => app($report));
    }

    public function find(string $key): ?Report
    {
        return $this->all()->first(fn (Report $report): bool => $report->key() === $key);
    }
}
