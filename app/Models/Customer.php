<?php

namespace App\Models;

use App\UserRole;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'business_name', 'business_type', 'contact_name', 'phone', 'whatsapp', 'email', 'address', 'reference', 'latitude', 'longitude', 'notes', 'is_active'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    public function routeStops(): HasMany
    {
        return $this->hasMany(RouteStop::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function receivables(): HasMany
    {
        return $this->hasMany(CustomerReceivable::class);
    }

    public function accountEntries(): HasMany
    {
        return $this->hasMany(CustomerAccountEntry::class);
    }

    public function creditProfile(): HasOne
    {
        return $this->hasOne(CustomerCreditProfile::class);
    }

    public function creditProfileHistory(): HasMany
    {
        return $this->hasMany(CustomerCreditProfileHistory::class);
    }

    public function creditAuthorizations(): HasMany
    {
        return $this->hasMany(CustomerCreditAuthorization::class);
    }

    public function salesRoutes(): BelongsToMany
    {
        return $this->belongsToMany(SalesRoute::class, 'route_stops')
            ->withPivot(['id', 'visit_day', 'visit_order', 'notes', 'is_active'])
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->when(
            $user->role === UserRole::Preventista,
            fn (Builder $customers): Builder => $customers->whereHas(
                'routeStops',
                fn (Builder $stops): Builder => $stops
                    ->where('is_active', true)
                    ->whereHas(
                        'salesRoute',
                        fn (Builder $routes): Builder => $routes
                            ->where('salesperson_id', $user->id)
                            ->where('is_active', true),
                    ),
            ),
        );
    }

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_active' => 'boolean',
        ];
    }
}
