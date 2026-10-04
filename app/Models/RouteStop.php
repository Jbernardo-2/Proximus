<?php

namespace App\Models;

use App\UserRole;
use App\Weekday;
use Database\Factories\RouteStopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->when(
            $user->role === UserRole::Preventista,
            fn (Builder $stops): Builder => $stops->whereHas(
                'salesRoute',
                fn (Builder $routes): Builder => $routes->where('salesperson_id', $user->id),
            ),
        );
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
