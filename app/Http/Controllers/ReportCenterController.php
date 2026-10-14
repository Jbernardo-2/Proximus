<?php

namespace App\Http\Controllers;

use App\CustomerCreditStatus;
use App\CustomerVisitType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CompanyProfile;
use App\Models\SalesRoute;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\OrderStatus;
use App\PaymentMethod;
use App\PaymentTerm;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportRegistry;
use App\UserRole;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportCenterController extends Controller
{
    private const int DOCUMENT_ROW_LIMIT = 1000;

    public function index(ReportRegistry $registry): View
    {
        return view('reports.center.index', [
            'groups' => $registry->all()->groupBy(fn (Report $report): string => $report->group()),
        ]);
    }

    public function show(Request $request, string $report, ReportRegistry $registry): View
    {
        $report = $this->report($registry, $report);
        $filters = ReportFilters::fromRequest($request);
        $query = $report->sortedQuery($filters);
        $rows = $query?->paginate(25)->withQueryString();
        $rows?->setCollection($rows->getCollection()->map(fn (object $row): array => $report->mapRow($row)));
        [$sortKey, $sortDirection] = $query === null ? [null, 'asc'] : $report->defaultSort();
        $usesRequestedSort = collect($report->columns())
            ->contains(fn (array $column): bool => ($column['sortable'] ?? false) && $column['key'] === $filters->sort);

        return view('reports.center.show', [
            'report' => $report,
            'filters' => $filters,
            'options' => $this->options($report),
            'summary' => $report->summary($filters),
            'charts' => $report->charts($filters),
            'columns' => $report->columns(),
            'rows' => $rows,
            'totals' => $this->totals($report, $filters),
            'sortKey' => $usesRequestedSort ? $filters->sort : $sortKey,
            'sortDirection' => $usesRequestedSort ? $filters->direction : $sortDirection,
            'currency' => $this->currency(),
        ]);
    }

    public function export(Request $request, string $report, ReportRegistry $registry): StreamedResponse
    {
        $report = $this->report($registry, $report);
        $filters = ReportFilters::fromRequest($request);
        $query = $report->sortedQuery($filters);
        abort_if($query === null, 404);
        $columns = $report->columns();

        return response()->streamDownload(function () use ($report, $query, $columns): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, array_column($columns, 'label'));

            foreach ($query->cursor() as $row) {
                $mapped = $report->mapRow($row);
                fputcsv($output, array_map(fn (array $column): mixed => match ($column['format']) {
                    'money', 'percent' => $mapped[$column['key']] === null ? '' : round((float) $mapped[$column['key']], 2),
                    'quantity' => (float) $mapped[$column['key']],
                    'date' => $mapped[$column['key']] === null ? '' : substr((string) $mapped[$column['key']], 0, 10),
                    default => $mapped[$column['key']] ?? '',
                }, $columns));
            }

            fclose($output);
        }, $this->filename($report, $filters, 'csv'), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function print(Request $request, string $report, ReportRegistry $registry): View
    {
        $report = $this->report($registry, $report);

        return view('reports.center.document', [
            ...$this->documentData($request, $report),
            'isPdf' => false,
        ]);
    }

    public function download(Request $request, string $report, ReportRegistry $registry): Response
    {
        $report = $this->report($registry, $report);
        $filters = ReportFilters::fromRequest($request);
        $html = view('reports.center.document', [
            ...$this->documentData($request, $report),
            'isPdf' => true,
        ])->render();
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter', 'landscape');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($report, $filters, 'pdf').'"',
        ]);
    }

    protected function report(ReportRegistry $registry, string $key): Report
    {
        return $registry->find($key) ?? abort(404);
    }

    /** @return array<string, mixed> */
    private function documentData(Request $request, Report $report): array
    {
        $filters = ReportFilters::fromRequest($request);
        $query = $report->sortedQuery($filters);
        $rows = $query?->limit(self::DOCUMENT_ROW_LIMIT + 1)->get()->map(fn (object $row): array => $report->mapRow($row));
        $company = CompanyProfile::query()->findOrFail(CompanyProfile::SINGLETON_ID);
        $options = $this->options($report);

        return [
            'report' => $report,
            'filters' => $filters,
            'company' => $company,
            'companyLogoDataUri' => $company->logo_path !== null && Storage::disk('public')->exists($company->logo_path)
                ? 'data:'.Storage::disk('public')->mimeType($company->logo_path).';base64,'.base64_encode(Storage::disk('public')->get($company->logo_path))
                : null,
            'generatedBy' => $request->user()->name,
            'appliedFilters' => collect($report->dimensions())
                ->filter(fn (string $dimension): bool => $filters->get($dimension) !== null)
                ->map(fn (string $dimension): string => $options[$dimension]['label'].': '.($options[$dimension]['items'][$filters->get($dimension)] ?? $filters->get($dimension)))
                ->when($filters->search !== '', fn ($applied) => $applied->push('Búsqueda: '.$filters->search))
                ->values(),
            'summary' => $report->summary($filters),
            'charts' => $report->charts($filters),
            'columns' => $report->columns(),
            'rows' => $rows?->take(self::DOCUMENT_ROW_LIMIT),
            'isTruncated' => $rows !== null && $rows->count() > self::DOCUMENT_ROW_LIMIT,
            'rowLimit' => self::DOCUMENT_ROW_LIMIT,
            'totals' => $this->totals($report, $filters),
            'currency' => $this->currency(),
        ];
    }

    /** @return array<string, float> */
    protected function totals(Report $report, ReportFilters $filters): array
    {
        $query = $report->query($filters);
        $keys = collect($report->columns())->filter(fn (array $column): bool => $column['total'] ?? false)->pluck('key');

        if ($query === null || $keys->isEmpty()) {
            return [];
        }

        $row = DB::query()
            ->fromSub($query, 'report_rows')
            ->selectRaw($keys->map(fn (string $key): string => "coalesce(sum({$key}), 0) as {$key}")->implode(', '))
            ->first();

        return $keys->mapWithKeys(fn (string $key): array => [$key => (float) $row->{$key}])->all();
    }

    /** @return array<string, array{label: string, items: array<string, string>}> */
    protected function options(Report $report): array
    {
        $sources = [
            'route' => ['Ruta', fn (): array => SalesRoute::query()->orderBy('name')->pluck('name', 'id')->all()],
            'salesperson' => ['Preventista', fn (): array => User::query()->where('role', UserRole::Preventista)->orderBy('name')->pluck('name', 'id')->all()],
            'driver' => ['Repartidor', fn (): array => User::query()->where('role', UserRole::Repartidor)->orderBy('name')->pluck('name', 'id')->all()],
            'payment_term' => ['Condición de pago', fn (): array => collect(PaymentTerm::cases())->mapWithKeys(fn (PaymentTerm $term): array => [$term->value => $term->label()])->all()],
            'status' => ['Estado', fn (): array => collect(OrderStatus::cases())->reject(fn (OrderStatus $status): bool => $status === OrderStatus::Draft)->mapWithKeys(fn (OrderStatus $status): array => [$status->value => $status->label()])->all()],
            'category' => ['Categoría', fn (): array => Category::query()->orderBy('name')->pluck('name', 'id')->all()],
            'brand' => ['Marca', fn (): array => Brand::query()->orderBy('name')->pluck('name', 'id')->all()],
            'warehouse' => ['Bodega', fn (): array => Warehouse::query()->orderBy('name')->pluck('name', 'id')->all()],
            'vehicle' => ['Vehículo', fn (): array => Vehicle::query()->orderBy('code')->get()->mapWithKeys(fn (Vehicle $vehicle): array => [$vehicle->id => trim($vehicle->code.' '.$vehicle->license_plate)])->all()],
            'method' => ['Método de pago', fn (): array => collect(PaymentMethod::cases())->mapWithKeys(fn (PaymentMethod $method): array => [$method->value => $method->label()])->all()],
            'visit_type' => ['Tipo de visita', fn (): array => collect(CustomerVisitType::cases())->mapWithKeys(fn (CustomerVisitType $type): array => [$type->value => $type->label()])->all()],
            'credit_status' => ['Estado del crédito', fn (): array => collect(CustomerCreditStatus::cases())->mapWithKeys(fn (CustomerCreditStatus $status): array => [$status->value => $status->label()])->all()],
        ];

        return collect($report->dimensions())
            ->mapWithKeys(fn (string $dimension): array => [$dimension => [
                'label' => $sources[$dimension][0],
                'items' => $sources[$dimension][1](),
            ]])
            ->all();
    }

    private function filename(Report $report, ReportFilters $filters, string $extension): string
    {
        return "{$report->key()}-{$filters->from->format('Ymd')}-{$filters->to->format('Ymd')}.{$extension}";
    }

    protected function currency(): string
    {
        return mb_strtoupper((string) config('proximus.currency', 'HNL'));
    }
}
