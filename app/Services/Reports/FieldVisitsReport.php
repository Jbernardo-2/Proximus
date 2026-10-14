<?php

namespace App\Services\Reports;

use App\CustomerVisitReason;
use App\CustomerVisitType;
use App\UserRole;
use App\VisitLocationStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FieldVisitsReport extends Report
{
    /** Beyond this distance from the customer's registered point a visit is flagged for review. */
    public const int FAR_FROM_CUSTOMER_METERS = 200;

    public function key(): string
    {
        return 'visitas-campo';
    }

    public function group(): string
    {
        return 'Control de campo';
    }

    public function title(): string
    {
        return 'Visitas y movimientos';
    }

    public function description(): string
    {
        return 'Cada pedido, visita sin pedido, entrega y cobro con su hora, ubicación y distancia al punto registrado del cliente.';
    }

    public function dimensions(): array
    {
        return ['salesperson', 'driver', 'visit_type'];
    }

    public function dateLabel(): ?string
    {
        return 'Fecha de la visita';
    }

    public function searchPlaceholder(): ?string
    {
        return 'Cliente o usuario';
    }

    public function summary(ReportFilters $filters): array
    {
        $row = $this->visits($filters)->selectRaw(implode(', ', [
            'count(*) as visits',
            'count(distinct v.customer_id) as customers',
            "coalesce(sum(case when v.location_status = 'captured' then 1 else 0 end), 0) as located",
            'coalesce(sum(case when v.distance_meters > ? then 1 else 0 end), 0) as far',
            "coalesce(sum(case when v.type = 'no_sale' then 1 else 0 end), 0) as no_sale",
            "coalesce(sum(case when v.type = 'order_taken' then 1 else 0 end), 0) as orders",
        ]), [self::FAR_FROM_CUSTOMER_METERS])->first();
        $visits = (int) $row->visits;

        return [
            ['label' => 'Visitas registradas', 'value' => $visits, 'format' => 'integer', 'hint' => $row->customers.' clientes'],
            ['label' => 'Con ubicación', 'value' => self::ratio((int) $row->located, $visits) ?? 0, 'format' => 'percent', 'hint' => ($visits - (int) $row->located).' sin ubicación'],
            ['label' => 'Lejos del cliente', 'value' => (int) $row->far, 'format' => 'integer', 'hint' => 'A más de '.self::FAR_FROM_CUSTOMER_METERS.' m del punto registrado'],
            ['label' => 'Pedidos tomados', 'value' => (int) $row->orders, 'format' => 'integer'],
            ['label' => 'Visitas sin pedido', 'value' => (int) $row->no_sale, 'format' => 'integer'],
        ];
    }

    public function charts(ReportFilters $filters): array
    {
        $users = $this->visits($filters)
            ->selectRaw('min(u.name) as label, count(*) as total')
            ->groupBy('v.user_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();
        $reasons = $this->visits($filters)
            ->where('v.type', CustomerVisitType::NoSale->value)
            ->selectRaw('v.reason, count(*) as total')
            ->groupBy('v.reason')
            ->orderByDesc('total')
            ->get();

        return [
            [
                'title' => 'Visitas por usuario',
                'type' => 'bars',
                'format' => 'integer',
                'labels' => $users->pluck('label')->all(),
                'series' => [['name' => 'Visitas', 'color' => ExecutiveSummaryReport::COLOR_DELIVERED, 'values' => $users->map(fn (object $row): int => (int) $row->total)->all()]],
            ],
            [
                'title' => 'Motivos de visitas sin pedido',
                'type' => 'bars',
                'format' => 'integer',
                'labels' => $reasons->map(fn (object $row): string => CustomerVisitReason::tryFrom((string) $row->reason)?->label() ?? 'Sin motivo')->all(),
                'series' => [['name' => 'Visitas', 'color' => ExecutiveSummaryReport::COLOR_CREDIT, 'values' => $reasons->map(fn (object $row): int => (int) $row->total)->all()]],
            ],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'occurred_at', 'label' => 'Fecha y hora', 'format' => 'datetime', 'sortable' => true],
            ['key' => 'user_name', 'label' => 'Usuario', 'format' => 'text', 'sortable' => true],
            ['key' => 'role', 'label' => 'Perfil', 'format' => 'text'],
            ['key' => 'customer_name', 'label' => 'Cliente', 'format' => 'text', 'sortable' => true],
            ['key' => 'type', 'label' => 'Tipo', 'format' => 'text', 'sortable' => true],
            ['key' => 'reason', 'label' => 'Motivo', 'format' => 'text'],
            ['key' => 'location_status', 'label' => 'Ubicación', 'format' => 'text', 'sortable' => true],
            ['key' => 'distance_meters', 'label' => 'Distancia al cliente (m)', 'format' => 'integer', 'sortable' => true],
            ['key' => 'accuracy_meters', 'label' => 'Precisión (m)', 'format' => 'quantity', 'sortable' => true],
            ['key' => 'coordinates', 'label' => 'Coordenadas', 'format' => 'text'],
            ['key' => 'received_at', 'label' => 'Recibido en servidor', 'format' => 'datetime', 'sortable' => true],
        ];
    }

    public function defaultSort(): array
    {
        return ['occurred_at', 'desc'];
    }

    public function query(ReportFilters $filters): ?Builder
    {
        return $this->visits($filters)
            ->when($filters->search !== '', fn (Builder $query) => $query->where(function (Builder $match) use ($filters): void {
                $match->where('c.business_name', 'like', "%{$filters->search}%")
                    ->orWhere('u.name', 'like', "%{$filters->search}%");
            }))
            ->selectRaw(implode(', ', [
                'v.occurred_at',
                'u.name as user_name',
                'u.role',
                'c.business_name as customer_name',
                'v.type',
                'v.reason',
                'v.location_status',
                'v.distance_meters',
                'v.accuracy_meters',
                'v.latitude',
                'v.longitude',
                'v.created_at as received_at',
            ]));
    }

    public function mapRow(object $row): array
    {
        $hasPosition = $row->latitude !== null && $row->longitude !== null;

        return [
            ...(array) $row,
            'role' => UserRole::tryFrom((string) $row->role)?->label(),
            'type' => CustomerVisitType::tryFrom((string) $row->type)?->label(),
            'reason' => CustomerVisitReason::tryFrom((string) $row->reason)?->label(),
            'location_status' => VisitLocationStatus::tryFrom((string) $row->location_status)?->label(),
            'coordinates' => $hasPosition ? (float) $row->latitude.', '.(float) $row->longitude : null,
            '_url' => $hasPosition ? 'https://www.google.com/maps?q='.(float) $row->latitude.','.(float) $row->longitude : null,
            '_link' => 'coordinates',
        ];
    }

    private function visits(ReportFilters $filters): Builder
    {
        $userId = $filters->get('salesperson') ?? $filters->get('driver');

        return self::inPeriod(
            DB::table('customer_visits as v')
                ->join('users as u', 'u.id', '=', 'v.user_id')
                ->join('customers as c', 'c.id', '=', 'v.customer_id'),
            'v.occurred_at',
            $filters,
        )
            ->when($userId !== null, fn (Builder $query) => $query->where('v.user_id', $userId))
            ->when($filters->get('visit_type') !== null, fn (Builder $query) => $query->where('v.type', $filters->get('visit_type')));
    }
}
