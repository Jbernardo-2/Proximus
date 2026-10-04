<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryRunItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_item_id' => $this->order_item_id,
            'product_id' => $this->product_id,
            'product_presentation_id' => $this->product_presentation_id,
            'product_sku' => $this->product_sku,
            'product_name' => $this->product_name,
            'presentation_name' => $this->presentation_name,
            'base_unit_symbol' => $this->base_unit_symbol,
            'conversion_factor' => $this->conversion_factor,
            'unit_price' => $this->unit_price,
            'requested_quantity' => $this->requested_quantity,
            'requested_base_quantity' => $this->requested_base_quantity,
            'prepared_quantity' => $this->prepared_quantity,
            'prepared_base_quantity' => $this->prepared_base_quantity,
            'loaded_quantity' => $this->loaded_quantity,
            'loaded_base_quantity' => $this->loaded_base_quantity,
            'delivered_quantity' => $this->delivered_quantity,
            'delivered_base_quantity' => $this->delivered_base_quantity,
            'returned_quantity' => $this->returned_quantity,
            'returned_base_quantity' => $this->returned_base_quantity,
            'damaged_quantity' => $this->damaged_quantity,
            'damaged_base_quantity' => $this->damaged_base_quantity,
            'missing_quantity' => $this->missing_quantity,
            'missing_base_quantity' => $this->missing_base_quantity,
            'delivered_line_total' => $this->delivered_line_total,
        ];
    }
}
