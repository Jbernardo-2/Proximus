<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use App\OrderStatus;
use App\UserRole;

class OrderPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->canViewOrders();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Order $order): bool
    {
        if (! $user->canViewOrders()) {
            return false;
        }

        return match ($user->role) {
            UserRole::Admin, UserRole::Supervisor => true,
            UserRole::Preventista => $order->salesperson_id === $user->id,
            UserRole::Bodeguero => in_array($order->status, [
                OrderStatus::Confirmed,
                OrderStatus::Assigned,
                OrderStatus::Loaded,
                OrderStatus::InTransit,
                OrderStatus::Delivered,
                OrderStatus::PartiallyDelivered,
                OrderStatus::NotDelivered,
            ], true),
            default => false,
        };
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->canManageOrders();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Order $order): bool
    {
        if (! $user->canManageOrders() || ! $order->isDraft()) {
            return false;
        }

        return in_array($user->role, [UserRole::Admin, UserRole::Supervisor], true)
            || $order->salesperson_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Order $order): bool
    {
        return false;
    }

    public function confirm(User $user, Order $order): bool
    {
        return $this->update($user, $order);
    }

    public function overridePrice(User $user, Order $order): bool
    {
        return $order->isDraft() && $user->canOverrideOrderPrices();
    }

    public function cancel(User $user, Order $order): bool
    {
        return $user->canManageOrderLifecycle() && in_array(
            $order->status,
            [OrderStatus::Draft, OrderStatus::Confirmed],
            true,
        );
    }

    public function reopen(User $user, Order $order): bool
    {
        return $user->canManageOrderLifecycle() && in_array(
            $order->status,
            [OrderStatus::Confirmed, OrderStatus::Cancelled],
            true,
        );
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Order $order): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Order $order): bool
    {
        return false;
    }
}
