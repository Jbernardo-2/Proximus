<?php

namespace App\Models;

use App\Weekday;
use Database\Factories\RouteStopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sales_route_id', 'customer_id', 'visit_day', 'visit_order', 'notes', 'is_active'])]
class RouteStop extends Model
{
    /** @use HasFactory<RouteStopFactory> */
    use HasFactory, HasUlids;

    public function salesRoute(): BelongsTo
    {
        return $this->belongsTo(SalesRoute::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    protected function casts(): array
    {
        return [
            'visit_day' => Weekday::class,
            'visit_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
