<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\ProductPresentation;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductPresentationRequest extends CatalogRequest
{
    public function rules(): array
    {
        $product = $this->route('product');
        $presentation = $this->route('presentation');
        $productId = $product instanceof Product ? $product->id : $presentation?->product_id;

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('product_presentations', 'name')
                    ->where('product_id', $productId)
                    ->ignore($presentation),
            ],
            'barcode' => ['nullable', 'string', 'max:80', Rule::unique('product_presentations', 'barcode')->ignore($presentation)],
            'conversion_factor' => ['required', 'numeric', 'min:0.000001', 'decimal:0,6'],
            'sale_price' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            'is_sellable' => ['required', 'boolean'],
            'is_purchasable' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('conversion_factor')) {
                return;
            }

            $presentation = $this->route('presentation');
            $product = $this->route('product');

            if ($presentation instanceof ProductPresentation) {
                $product = $presentation->product;

                if ($presentation->is_base && bccomp((string) $this->input('conversion_factor'), '1', 6) !== 0) {
                    $validator->errors()->add('conversion_factor', 'La presentación base siempre equivale a 1 unidad base.');
                }

                if ($presentation->is_base && ! $this->boolean('is_active')) {
                    $validator->errors()->add('is_active', 'La presentación base no puede desactivarse.');
                }
            }

            if ($product instanceof Product && ! $product->allows_decimal) {
                $factor = (string) $this->input('conversion_factor');

                if (bccomp($factor, bcadd($factor, '0', 0), 6) !== 0) {
                    $validator->errors()->add('conversion_factor', 'Este producto se maneja en unidades enteras; el contenido debe ser un número entero.');
                }
            }
        }];
    }
}
