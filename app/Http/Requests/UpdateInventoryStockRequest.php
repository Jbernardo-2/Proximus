<?php

namespace App\Http\Requests;

use App\Models\InventoryStock;
use Illuminate\Validation\Validator;

class UpdateInventoryStockRequest extends InventoryRequest
{
    public function authorize(): bool
    {
        $stock = $this->route('inventoryStock');

        return $stock instanceof InventoryStock && ($this->user()?->can('update', $stock) ?? false);
    }

    public function rules(): array
    {
        return ['reorder_point' => ['required', 'numeric', 'min:0', 'decimal:0,6']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('reorder_point')) {
                return;
            }

            /** @var InventoryStock|null $stock */
            $stock = $this->route('inventoryStock');
            $reorderPoint = (string) $this->input('reorder_point');

            if ($stock !== null
                && ! $stock->product->allows_decimal
                && bccomp($reorderPoint, bcadd($reorderPoint, '0', 0), 6) !== 0) {
                $validator->errors()->add('reorder_point', 'Este producto solo admite cantidades enteras.');
            }
        }];
    }
}
