<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class CatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-catalog') ?? false;
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'description' => 'descripción',
            'image' => 'imagen del producto',
            'is_active' => 'estado activo',
            'category_id' => 'categoría',
            'brand_id' => 'marca',
            'base_unit_id' => 'unidad base',
            'conversion_factor' => 'contenido en unidad base',
            'sale_price' => 'precio de venta',
            'unit_price' => 'precio unitario',
            'cost_price' => 'costo de compra',
            'min_quantity' => 'cantidad mínima',
            'max_quantity' => 'cantidad máxima',
            'product_presentation_id' => 'presentación',
            'supplier_id' => 'proveedor',
        ];
    }
}
