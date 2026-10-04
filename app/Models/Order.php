<?php

namespace App\Models;

use App\OrderStatus;
use App\PaymentTerm;
use App\UserRole;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['order_number', 'client_reference', 'customer_id', 'sales_route_id', 'route_stop_id', 'salesperson_id', 'created_by', 'order_date', 'requested_delivery_date', 'payment_term', 'status', 'currency', 'customer_code', 'customer_name', 'customer_address', 'route_code', 'route_name', 'route_visit_day', 'route_visit_order', 'salesperson_name', 'notes', 'subtotal', 'total', 'confirmed_at', 'confirmed_by', 'cancelled_at', 'cancelled_by', 'cancellation_reason'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, HasUlids;

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function salesRoute(): BelongsTo
    {
        return $this->belongsTo(SalesRoute::class)->withTrashed();
    }

    public function routeStop(): BelongsTo
    {
        return $this->belongsTo(RouteStop::class);
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            UserRole::Admin, UserRole::Supervisor => $query,
            UserRole::Preventista => $query->where('salesperson_id', $user->id),
            UserRole::Bodeguero => $query->where('status', OrderStatus::Confirmed->value),
            default => $query->whereNull('id'),
        };
    }

    public function isDraft(): bool
    {
        return $this->status === OrderStatus::Draft;
    }

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'requested_delivery_date' => 'date',
            'payment_term' => PaymentTerm::class,
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:4',
            'total' => 'decimal:4',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'route_visit_day' => 'integer',
            'route_visit_order' => 'integer',
        ];
    }
}
