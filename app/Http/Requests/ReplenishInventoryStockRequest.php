<?php

namespace App\Http\Requests;

use App\Models\InventoryStock;
use Illuminate\Validation\Validator;

class ReplenishInventoryStockRequest extends InventoryRequest
{
    public function authorize(): bool
    {
        $stock = $this->route('inventoryStock');

        return $stock instanceof InventoryStock
            && ($this->user()?->canOperateInventory() ?? false);
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,6'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('quantity')) {
                return;
            }

            /** @var InventoryStock|null $stock */
            $stock = $this->route('inventoryStock');
            $product = $stock?->product;

            if ($product === null) {
                return;
            }

            if ($product->tracks_lots || $product->tracks_expiration) {
                $validator->errors()->add(
                    'quantity',
                    'Este producto requiere una entrada detallada para registrar lote o vencimiento.',
                );

                return;
            }

            $quantity = (string) $this->input('quantity');

            if (! $product->allows_decimal
                && bccomp($quantity, bcadd($quantity, '0', 0), 6) !== 0) {
                $validator->errors()->add('quantity', 'Este producto solo admite cantidades enteras.');
            }
        }];
    }
}
