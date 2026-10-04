<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class DeliveryRequest extends FormRequest
{
    public function attributes(): array
    {
        return [
            'warehouse_id' => 'bodega',
            'driver_id' => 'repartidor',
            'vehicle_id' => 'vehículo',
            'scheduled_date' => 'fecha programada',
            'client_reference' => 'referencia de sincronización',
            'order_id' => 'pedido',
            'visit_order' => 'orden de visita',
            'items' => 'productos',
            'items.*.id' => 'línea de carga',
            'items.*.prepared_quantity' => 'cantidad preparada',
            'items.*.delivered_quantity' => 'cantidad entregada',
            'items.*.returned_quantity' => 'cantidad devuelta',
            'items.*.damaged_quantity' => 'cantidad dañada',
            'items.*.missing_quantity' => 'cantidad faltante',
            'outcome_reason' => 'motivo de entrega incompleta',
            'outcome_notes' => 'observaciones de entrega',
            'credit_reason' => 'justificación del saldo',
            'receiver_name' => 'persona que recibió',
            'method' => 'método de pago',
            'amount' => 'monto',
            'reference' => 'referencia del pago',
            'cash_declared' => 'efectivo declarado',
            'settlement_notes' => 'observaciones de liquidación',
            'reason' => 'motivo',
        ];
    }

    protected function nullableString(string $key): ?string
    {
        $value = $this->string($key)->trim()->toString();

        return $value !== '' ? $value : null;
    }

    protected function nullableIdentifier(string $key): mixed
    {
        $value = $this->input($key);

        return $value === null || $value === '' ? null : $value;
    }
}
