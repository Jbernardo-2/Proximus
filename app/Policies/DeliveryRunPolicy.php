<?php

namespace App\Policies;

use App\DeliveryRunStatus;
use App\Models\DeliveryRun;
use App\Models\User;
use App\UserRole;

class DeliveryRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canViewDeliveries();
    }

    public function view(User $user, DeliveryRun $deliveryRun): bool
    {
        if (! $user->canViewDeliveries()) {
            return false;
        }

        return match ($user->role) {
            UserRole::Admin, UserRole::Supervisor, UserRole::Bodeguero => true,
            UserRole::Repartidor => $deliveryRun->driver_id === $user->id,
            UserRole::Preventista => $deliveryRun->runOrders()
                ->whereHas('order', fn ($orders) => $orders->where('salesperson_id', $user->id))
                ->exists(),
        };
    }

    public function create(User $user): bool
    {
        return $user->canManageDeliveries();
    }

    public function update(User $user, DeliveryRun $deliveryRun): bool
    {
        return $deliveryRun->isDraft() && $user->canManageDeliveries();
    }

    public function assignOrders(User $user, DeliveryRun $deliveryRun): bool
    {
        return $this->update($user, $deliveryRun);
    }

    public function startPreparation(User $user, DeliveryRun $deliveryRun): bool
    {
        return $deliveryRun->isDraft() && $user->canPrepareDeliveries();
    }

    public function prepare(User $user, DeliveryRun $deliveryRun): bool
    {
        return $deliveryRun->isPreparing() && $user->canPrepareDeliveries();
    }

    public function load(User $user, DeliveryRun $deliveryRun): bool
    {
        return $this->prepare($user, $deliveryRun);
    }

    public function depart(User $user, DeliveryRun $deliveryRun): bool
    {
        return $deliveryRun->status === DeliveryRunStatus::Loaded
            && $user->canExecuteDeliveries()
            && $this->isAssignedDriverOrSupervisor($user, $deliveryRun);
    }

    public function execute(User $user, DeliveryRun $deliveryRun): bool
    {
        return $deliveryRun->canRecordRouteActivity()
            && $user->canExecuteDeliveries()
            && $this->isAssignedDriverOrSupervisor($user, $deliveryRun);
    }

    public function settle(User $user, DeliveryRun $deliveryRun): bool
    {
        return $deliveryRun->status === DeliveryRunStatus::AwaitingSettlement
            && $user->canSettleDeliveries();
    }

    public function cancel(User $user, DeliveryRun $deliveryRun): bool
    {
        return in_array($deliveryRun->status, [
            DeliveryRunStatus::Draft,
            DeliveryRunStatus::Preparing,
        ], true) && $user->canManageDeliveries();
    }

    private function isAssignedDriverOrSupervisor(User $user, DeliveryRun $deliveryRun): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Supervisor], true)
            || $deliveryRun->driver_id === $user->id;
    }
}
