<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class OperationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->ability()) ?? false;
    }

    public function attributes(): array
    {
        return [
            'code' => 'código',
            'business_name' => 'nombre del negocio',
            'business_type' => 'tipo de negocio',
            'contact_name' => 'persona de contacto',
            'phone' => 'teléfono',
            'whatsapp' => 'WhatsApp',
            'email' => 'correo electrónico',
            'address' => 'dirección',
            'reference' => 'referencia de ubicación',
            'latitude' => 'latitud',
            'longitude' => 'longitud',
            'notes' => 'notas',
            'name' => 'nombre',
            'description' => 'descripción',
            'salesperson_id' => 'preventista',
            'driver_id' => 'repartidor',
            'customer_id' => 'cliente',
            'visit_day' => 'día de visita',
            'visit_order' => 'orden de visita',
            'is_active' => 'estado activo',
        ];
    }

    abstract protected function ability(): string;
}
