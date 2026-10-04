<?php

namespace App\Http\Requests;

use App\Models\Order;
use App\Models\ProductPresentation;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class OrderItemQuoteRequest extends OrderRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof Order && ($this->user()?->can('update', $order) ?? false);
    }

    public function rules(): array
    {
        return [
            'product_presentation_id' => [
                'required',
                'ulid',
                Rule::exists('product_presentations', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('is_active', true)
                        ->where('is_sellable', true)
                        ->whereNull('deleted_at'),
                ),
            ],
            'quantity' => ['required', 'numeric', 'min:0.000001', 'decimal:0,6'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['product_presentation_id', 'quantity'])) {
                return;
            }

            $presentation = ProductPresentation::query()
                ->with('product')
                ->find($this->input('product_presentation_id'));

            if ($presentation === null || ! $presentation->product?->is_active) {
                $validator->errors()->add('product_presentation_id', 'La presentación no está disponible para venta.');

                return;
            }

            $allowsDecimal = $presentation->is_base && $presentation->product->allows_decimal;
            $quantity = (string) $this->input('quantity');

            if (! $allowsDecimal && bccomp($quantity, bcadd($quantity, '0', 0), 6) !== 0) {
                $validator->errors()->add('quantity', 'Esta presentación se vende en cantidades enteras.');
            }
        }];
    }
}
