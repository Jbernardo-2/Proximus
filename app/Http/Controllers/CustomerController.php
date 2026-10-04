<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();
        $businessType = $request->string('business_type')->trim()->toString();

        return view('operations.customers.index', [
            'customers' => Customer::query()
                ->withCount(['routeStops' => fn ($query) => $query->visibleTo($user)])
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($builder) use ($search): void {
                        $builder->where('business_name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%")
                            ->orWhere('contact_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('whatsapp', 'like', "%{$search}%");
                    });
                })
                ->when($businessType !== '', fn ($query) => $query->where('business_type', $businessType))
                ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('is_active', $status === 'active'))
                ->orderByDesc('is_active')
                ->orderBy('business_name')
                ->orderBy('id')
                ->paginate(20)
                ->withQueryString(),
            'businessTypes' => Customer::query()
                ->whereNotNull('business_type')
                ->where('business_type', '<>', '')
                ->distinct()
                ->orderBy('business_type')
                ->pluck('business_type'),
            'search' => $search,
            'selectedStatus' => $status,
            'selectedBusinessType' => $businessType,
        ]);
    }

    public function create(): View
    {
        return view('operations.customers.form', ['customer' => new Customer]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = Customer::query()->create($request->validated());

        return redirect()->route('customers.show', $customer)->with('success', 'Cliente creado correctamente.');
    }

    public function show(Request $request, Customer $customer): View
    {
        /** @var User $user */
        $user = $request->user();
        $customer->load([
            'routeStops' => fn ($query) => $query
                ->with('salesRoute')
                ->visibleTo($user)
                ->orderBy('visit_day')
                ->orderBy('visit_order')
                ->orderBy('id'),
        ]);

        return view('operations.customers.show', compact('customer'));
    }

    public function edit(Customer $customer): View
    {
        return view('operations.customers.form', compact('customer'));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return redirect()->route('customers.show', $customer)->with('success', 'Cliente actualizado correctamente.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        if ($customer->routeStops()->exists()) {
            return back()->with('error', 'No se puede eliminar un cliente asignado a rutas. Puedes desactivarlo o retirar primero sus visitas.');
        }

        $customer->delete();

        return redirect()->route('customers.index')->with('success', 'Cliente eliminado correctamente.');
    }
}
