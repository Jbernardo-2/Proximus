<?php

namespace App\Policies;

use App\DeliveryRunStatus;
use App\Models\DeliveryRun;
use App\Models\SalesRoute;
use App\Models\User;
use App\UserRole;
use Illuminate\Auth\Access\Response;

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

    public function viewRouteReport(User $user, DeliveryRun $deliveryRun, SalesRoute $salesRoute): bool
    {
        if (! $user->canViewRouteReports()) {
            return false;
        }

        $runContainsRoute = $deliveryRun->runOrders()
            ->whereHas('order', fn ($orders) => $orders->where('sales_route_id', $salesRoute->id))
            ->exists();

        if (! $runContainsRoute) {
            return false;
        }

        return match ($user->role) {
            UserRole::Admin, UserRole::Supervisor, UserRole::Bodeguero => true,
            UserRole::Repartidor => $deliveryRun->driver_id === $user->id,
            UserRole::Preventista => $salesRoute->salesperson_id === $user->id,
        };
    }

    public function update(User $user, DeliveryRun $deliveryRun): bool
    {
        return $deliveryRun->isDraft() && $user->canManageDeliveries();
    }

    public function assignOrders(User $user, DeliveryRun $deliveryRun): bool
    {
        return $this->update($user, $deliveryRun);
    }

    /** While assembling, whoever assembles; while preparing, only a supervisor (the keeper asks for it). */
    public function removeOrder(User $user, DeliveryRun $deliveryRun): bool
    {
        return $this->assignOrders($user, $deliveryRun)
            || ($deliveryRun->isPreparing() && $user->canSettleDeliveries());
    }

    public function startPreparation(User $user, DeliveryRun $deliveryRun): bool
    {
        return $deliveryRun->isDraft() && $user->canPrepareDeliveries();
    }

    public function prepare(User $user, DeliveryRun $deliveryRun): Response
    {
        if (! $deliveryRun->isPreparing() || ! $user->canPrepareDeliveries()) {
            return Response::deny();
        }

        // One warehouse keeper prepares a run; a supervisor may take it over.
        if ($user->role === UserRole::Bodeguero
            && $deliveryRun->preparation_started_by !== null
            && (int) $deliveryRun->preparation_started_by !== $user->id) {
            $preparer = User::query()->find($deliveryRun->preparation_started_by)?->name ?? 'otro bodeguero';

            return Response::deny("La jornada {$deliveryRun->run_number} la está preparando {$preparer}.");
        }

        return Response::allow();
    }

    public function load(User $user, DeliveryRun $deliveryRun): Response
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
