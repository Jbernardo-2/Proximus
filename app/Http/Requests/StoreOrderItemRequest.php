<?php

namespace App\Http\Requests;

use App\Models\Order;
use App\Models\ProductPresentation;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOrderItemRequest extends OrderRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof Order && ($this->user()?->can('update', $order) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'unit_price' => $this->nullableIdentifier('unit_price'),
            'override_reason' => $this->nullableString('override_reason'),
            'notes' => $this->nullableString('notes'),
        ]);
    }

    public function rules(): array
    {
        /** @var Order $order */
        $order = $this->route('order');
        $item = $this->route('orderItem');

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
                Rule::unique('order_items', 'product_presentation_id')
                    ->where('order_id', $order->id)
                    ->ignore($item),
            ],
            'quantity' => ['required', 'numeric', 'min:0.000001', 'decimal:0,6'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'override_reason' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
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

            if ($presentation === null) {
                return;
            }

            if (! $presentation->product?->is_active) {
                $validator->errors()->add(
                    'product_presentation_id',
                    'El producto de esta presentación no está activo.',
                );
            }

            $allowsDecimal = $presentation->is_base && $presentation->product?->allows_decimal;
            $quantity = (string) $this->input('quantity');

            if (! $allowsDecimal && bccomp($quantity, bcadd($quantity, '0', 0), 6) !== 0) {
                $validator->errors()->add('quantity', 'Esta presentación se vende en cantidades enteras.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'product_presentation_id.unique' => 'La presentación ya está en el pedido; edita su línea para cambiar la cantidad.',
        ];
    }
}
