<?php

namespace Database\Factories;

use App\InventoryDocumentStatus;
use App\InventoryDocumentType;
use App\Models\InventoryDocument;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryDocument> */
class InventoryDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'document_number' => fake()->unique()->numerify('REC-2026-######'),
            'warehouse_id' => Warehouse::factory(),
            'supplier_id' => null,
            'type' => InventoryDocumentType::Receipt,
            'status' => InventoryDocumentStatus::Draft,
            'occurred_on' => now()->toDateString(),
            'external_reference' => null,
            'notes' => null,
            'created_by' => User::factory(),
            'posted_at' => null,
            'posted_by' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'cancellation_reason' => null,
        ];
    }
}
