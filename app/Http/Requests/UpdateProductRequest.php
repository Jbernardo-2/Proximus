<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductRequest extends CatalogRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'sku' => $this->string('sku')->trim()->upper()->toString(),
            'slug' => Str::slug($this->string('name')->toString()),
            'tracks_lots' => $this->boolean('tracks_lots'),
            'tracks_expiration' => $this->boolean('tracks_expiration'),
        ]);
    }

    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'category_id' => ['required', 'ulid', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'brand_id' => ['nullable', 'ulid', Rule::exists('brands', 'id')->whereNull('deleted_at')],
            'base_unit_id' => ['required', 'ulid', Rule::exists('measurement_units', 'id')->whereNull('deleted_at')],
            'sku' => ['required', 'string', 'max:80', Rule::unique('products', 'sku')->ignore($product)],
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['required', 'string', 'max:200', Rule::unique('products', 'slug')->ignore($product)],
            'description' => ['nullable', 'string', 'max:4000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'remove_image' => ['sometimes', 'boolean'],
            'allows_decimal' => ['required', 'boolean'],
            'tracks_lots' => ['required', 'boolean'],
            'tracks_expiration' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->boolean('tracks_expiration') && ! $this->boolean('tracks_lots')) {
                $validator->errors()->add('tracks_lots', 'Para controlar vencimientos también debes activar el control por lotes.');
            }

            $product = $this->route('product');

            if (! $product instanceof Product
                || $validator->errors()->has('base_unit_id')
                || (string) $this->input('base_unit_id') === (string) $product->base_unit_id) {
                return;
            }

            $hasInventoryHistory = $product->inventoryMovements()->exists()
                || $product->inventoryReservations()->exists()
                || $product->orderItems()->exists()
                || $product->inventoryStocks()
                    ->where(function ($query): void {
                        $query->where('quantity_on_hand', '!=', 0)
                            ->orWhere('quantity_reserved', '!=', 0)
                            ->orWhere('reorder_point', '!=', 0);
                    })
                    ->exists();

            if ($hasInventoryHistory) {
                $validator->errors()->add(
                    'base_unit_id',
                    'La unidad base no puede cambiar después de usar el producto en pedidos o inventario. Crea un producto nuevo para conservar la trazabilidad.',
                );
            }
        }];
    }
}
