<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class OrderRequest extends FormRequest
{
    public function attributes(): array
    {
        return [
            'client_reference' => 'referencia de sincronización',
            'customer_id' => 'cliente',
            'route_stop_id' => 'visita programada',
            'salesperson_id' => 'preventista',
            'order_date' => 'fecha del pedido',
            'requested_delivery_date' => 'fecha solicitada de entrega',
            'payment_term' => 'condición de pago',
            'notes' => 'notas',
            'product_presentation_id' => 'presentación',
            'quantity' => 'cantidad',
            'unit_price' => 'precio unitario',
            'override_reason' => 'motivo del cambio de precio',
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
