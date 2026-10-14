<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ReportCenterController;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The supervisor's report center as data. PDF and CSV come from the inherited
 * download and export actions, with the same filters as query parameters.
 */
class ReportController extends ReportCenterController
{
    public function catalog(ReportRegistry $registry): JsonResponse
    {
        return response()->json([
            'data' => $registry->all()->map(fn (Report $report): array => [
                'key' => $report->key(),
                'group' => $report->group(),
                'title' => $report->title(),
                'description' => $report->description(),
            ])->values()->all(),
            'periods' => ReportFilters::PERIODS,
            'bases' => ReportFilters::BASES,
        ]);
    }

    public function data(Request $request, string $report, ReportRegistry $registry): JsonResponse
    {
        $report = $this->report($registry, $report);
        $filters = ReportFilters::fromRequest($request);
        $rows = $report->sortedQuery($filters)?->paginate(min(max($request->integer('per_page', 25), 1), 200));

        return response()->json([
            'report' => [
                'key' => $report->key(),
                'title' => $report->title(),
                'description' => $report->description(),
                'uses_period' => $report->usesPeriod(),
                'date_label' => $report->dateLabel(),
                'search_placeholder' => $report->searchPlaceholder(),
            ],
            'filters' => [
                'period' => $filters->period,
                'period_label' => $filters->periodLabel(),
                'from' => $filters->from->toDateString(),
                'to' => $filters->to->toDateString(),
                'basis' => $filters->basis,
                'search' => $filters->search,
                'values' => (object) $filters->values,
                'options' => (object) $this->options($report),
            ],
            'currency' => $this->currency(),
            'summary' => $report->summary($filters),
            'charts' => $report->charts($filters),
            'columns' => $report->columns(),
            'rows' => $rows === null ? [] : $rows->getCollection()->map(fn (object $row): array => $report->mapRow($row))->all(),
            'totals' => (object) $this->totals($report, $filters),
            'meta' => $rows === null ? null : [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'total' => $rows->total(),
            ],
        ]);
    }
}
