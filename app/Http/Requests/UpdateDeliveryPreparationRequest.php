<?php

namespace App\Http\Requests;

use App\Models\DeliveryRun;
use App\Models\DeliveryRunItem;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

class UpdateDeliveryPreparationRequest extends DeliveryRequest
{
    public function authorize(): bool|Response
    {
        $deliveryRun = $this->route('deliveryRun');

        return $deliveryRun instanceof DeliveryRun
            ? Gate::forUser($this->user())->inspect('prepare', $deliveryRun)
            : false;
    }

    /** A phone that assembled the run offline only knows the order lines, not the run lines. */
    protected function prepareForValidation(): void
    {
        $deliveryRun = $this->route('deliveryRun');

        if (! $deliveryRun instanceof DeliveryRun || ! is_array($this->input('items'))) {
            return;
        }

        $byOrderItem = DeliveryRunItem::query()
            ->whereHas('deliveryRunOrder', fn ($orders) => $orders->where('delivery_run_id', $deliveryRun->id))
            ->pluck('id', 'order_item_id');

        $this->merge(['items' => collect($this->input('items'))->map(fn ($item): mixed => is_array($item) && ! isset($item['id']) && isset($item['order_item_id'])
            ? [...$item, 'id' => $byOrderItem->get($item['order_item_id'])]
            : $item)->all()]);
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'ulid', 'distinct', 'exists:delivery_run_items,id'],
            'items.*.prepared_quantity' => ['required', 'numeric', 'min:0', 'decimal:0,6'],
        ];
    }
}
