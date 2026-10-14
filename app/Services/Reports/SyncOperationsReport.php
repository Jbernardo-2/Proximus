<?php

namespace App\Services\Reports;

use App\Models\SyncOperation;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class SyncOperationsReport extends Report
{
    private const array TYPES = [
        'order.create' => 'Pedido',
        'visit.no_sale' => 'Visita sin pedido',
        'delivery.outcome' => 'Resultado de entrega',
        'delivery.payment' => 'Cobro de entrega',
        'customer.payment' => 'Abono a cuenta',
    ];

    private const array STATUSES = [
        SyncOperation::ACCEPTED => 'Aplicada',
        SyncOperation::REJECTED => 'Rechazada',
        SyncOperation::FAILED => 'Error del servidor',
    ];

    public function key(): string
    {
        return 'sincronizacion';
    }

    public function group(): string
    {
        return 'Control de campo';
    }

    public function title(): string
    {
        return 'Operaciones sincronizadas';
    }

    public function description(): string
    {
        return 'Lo registrado en teléfonos sin conexión y enviado después: qué se aplicó, qué se rechazó y por qué.';
    }

    public function dimensions(): array
    {
        return ['salesperson', 'driver'];
    }

    public function dateLabel(): ?string
    {
        return 'Fecha de sincronización';
    }

    public function searchPlaceholder(): ?string
    {
        return 'Usuario, dispositivo o referencia';
    }

    public function summary(ReportFilters $filters): array
    {
        $row = $this->operations($filters)->selectRaw(implode(', ', [
            'count(*) as total',
            "coalesce(sum(case when s.status = 'accepted' then 1 else 0 end), 0) as accepted",
            "coalesce(sum(case when s.status = 'rejected' then 1 else 0 end), 0) as rejected",
            "coalesce(sum(case when s.status = 'failed' then 1 else 0 end), 0) as failed",
            'count(distinct s.device_name) as devices',
        ]))->first();

        return [
            ['label' => 'Operaciones recibidas', 'value' => (int) $row->total, 'format' => 'integer', 'hint' => $row->devices.' dispositivos'],
            ['label' => 'Aplicadas', 'value' => (int) $row->accepted, 'format' => 'integer'],
            ['label' => 'Rechazadas', 'value' => (int) $row->rejected, 'format' => 'integer', 'hint' => 'Requieren revisión con el usuario'],
            ['label' => 'Errores del servidor', 'value' => (int) $row->failed, 'format' => 'integer', 'hint' => 'El teléfono las reintenta'],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'processed_at', 'label' => 'Sincronizada', 'format' => 'datetime', 'sortable' => true],
            ['key' => 'occurred_at', 'label' => 'Registrada en el teléfono', 'format' => 'datetime', 'sortable' => true],
            ['key' => 'user_name', 'label' => 'Usuario', 'format' => 'text', 'sortable' => true],
            ['key' => 'device_name', 'label' => 'Dispositivo', 'format' => 'text', 'sortable' => true],
            ['key' => 'type', 'label' => 'Operación', 'format' => 'text', 'sortable' => true],
            ['key' => 'status', 'label' => 'Resultado', 'format' => 'text', 'sortable' => true],
            ['key' => 'message', 'label' => 'Motivo', 'format' => 'text'],
            ['key' => 'attempts', 'label' => 'Intentos', 'format' => 'integer', 'sortable' => true],
            ['key' => 'client_reference', 'label' => 'Referencia', 'format' => 'text'],
        ];
    }

    public function defaultSort(): array
    {
        return ['processed_at', 'desc'];
    }

    public function query(ReportFilters $filters): ?Builder
    {
        return $this->operations($filters)
            ->when($filters->search !== '', fn (Builder $query) => $query->where(function (Builder $match) use ($filters): void {
                $match->where('u.name', 'like', "%{$filters->search}%")
                    ->orWhere('s.device_name', 'like', "%{$filters->search}%")
                    ->orWhere('s.client_reference', 'like', "%{$filters->search}%");
            }))
            ->selectRaw('s.processed_at, s.occurred_at, u.name as user_name, s.device_name, s.type, s.status, s.response, s.attempts, s.client_reference');
    }

    public function mapRow(object $row): array
    {
        $response = json_decode((string) $row->response, true);
        $errors = collect($response['errors'] ?? [])->flatten()->filter()->implode(' ');

        return [
            ...(array) $row,
            'type' => self::TYPES[$row->type] ?? $row->type,
            'status' => self::STATUSES[$row->status] ?? $row->status,
            'message' => $row->status === SyncOperation::ACCEPTED ? null : ($errors !== '' ? $errors : ($response['message'] ?? null)),
        ];
    }

    private function operations(ReportFilters $filters): Builder
    {
        $userId = $filters->get('salesperson') ?? $filters->get('driver');

        return self::inPeriod(
            DB::table('sync_operations as s')->join('users as u', 'u.id', '=', 's.user_id'),
            's.processed_at',
            $filters,
        )->when($userId !== null, fn (Builder $query) => $query->where('s.user_id', $userId));
    }
}
