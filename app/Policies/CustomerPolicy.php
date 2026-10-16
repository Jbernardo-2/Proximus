<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use App\UserRole;
use Illuminate\Auth\Access\Response;

class CustomerPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->canManageCustomers();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Customer $customer): bool
    {
        return $user->canManageCustomers()
            && Customer::query()->visibleTo($user)->whereKey($customer)->exists();
    }

    /**
     * Record an account payment: whoever manages customers and sees this one, or a
     * driver who carries an order for them right now (they collect old debts too).
     */
    public function collect(User $user, Customer $customer): Response
    {
        if (! $user->canRecordCustomerPayments()) {
            return Response::deny('Tu usuario no puede registrar abonos.');
        }

        if ($user->role === UserRole::Repartidor) {
            return Customer::query()->carriedBy($user)->whereKey($customer)->exists()
                ? Response::allow()
                : Response::deny('El abono no se registró: este cliente no está en tus jornadas activas.');
        }

        return $this->view($user, $customer) ? Response::allow() : Response::deny('El abono no se registró: este cliente no está en tus rutas.');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->canManageCustomers();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Customer $customer): bool
    {
        return $this->view($user, $customer);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Customer $customer): bool
    {
        return $user->is_active
            && in_array($user->role, [UserRole::Admin, UserRole::Supervisor], true);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Customer $customer): bool
    {
        return $this->delete($user, $customer);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Customer $customer): bool
    {
        return false;
    }
}
