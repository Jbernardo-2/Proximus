<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Validation\Validator;

class ConversionPreviewRequest extends CatalogRequest
{
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'min:0.000001', 'decimal:0,6'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('quantity')) {
                return;
            }

            $product = $this->route('product');

            if ($product instanceof Product && ! $product->allows_decimal) {
                $quantity = (string) $this->input('quantity');

                if (bccomp($quantity, bcadd($quantity, '0', 0), 6) !== 0) {
                    $validator->errors()->add('quantity', 'Este producto se maneja en cantidades enteras.');
                }
            }
        }];
    }
}
