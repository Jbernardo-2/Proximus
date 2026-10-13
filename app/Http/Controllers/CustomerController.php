<?php

namespace App\Http\Controllers;

use App\CustomerCreditStatus;
use App\CustomerReceivableStatus;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\User;
use App\PaymentMethod;
use App\Services\CustomerCreditService;
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
                ->with('creditProfile')
                ->withCount(['routeStops' => fn ($query) => $query->visibleTo($user)])
                ->withSum([
                    'receivables as outstanding_balance' => fn ($query) => $query
                        ->whereIn('status', [
                            CustomerReceivableStatus::Open->value,
                            CustomerReceivableStatus::PartiallyPaid->value,
                        ]),
                ], 'outstanding_amount')
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
        Customer::query()->create($request->validated());

        return redirect()->route('customers.index')->with('success', 'Cliente creado correctamente.');
    }

    public function show(
        Request $request,
        Customer $customer,
        CustomerCreditService $creditService,
    ): View {
        /** @var User $user */
        $user = $request->user();
        $customer->load([
            'routeStops' => fn ($query) => $query
                ->with('salesRoute')
                ->visibleTo($user)
                ->orderBy('visit_day')
                ->orderBy('visit_order')
                ->orderBy('id'),
            'receivables' => fn ($query) => $query
                ->with(['order', 'allocations.accountEntry'])
                ->orderByDesc('opened_at')
                ->orderByDesc('id'),
            'accountEntries' => fn ($query) => $query
                ->with(['order', 'createdBy', 'paymentLines', 'allocations.receivable.order', 'reversalOf'])
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->limit(100),
            'creditProfile.approvedBy',
            'creditProfile.updatedBy',
            'creditProfileHistory' => fn ($query) => $query
                ->with('changedBy')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(25),
            'creditAuthorizations' => fn ($query) => $query
                ->with(['authorizedBy', 'order'])
                ->orderByDesc('authorized_at')
                ->orderByDesc('id')
                ->limit(25),
        ]);
        $accountTotals = $customer->accountEntries()
            ->selectRaw('coalesce(sum(debit_amount), 0) as purchases_total, coalesce(sum(credit_amount), 0) as payments_total')
            ->first();
        $creditSummary = $creditService->summary($customer);

        return view('operations.customers.show', [
            'customer' => $customer,
            'openReceivables' => $customer->receivables
                ->filter(fn ($receivable): bool => $receivable->status->isOutstanding()),
            'purchasesTotal' => (string) ($accountTotals?->purchases_total ?? '0'),
            'paymentsTotal' => (string) ($accountTotals?->payments_total ?? '0'),
            'accountBalance' => $creditSummary['ledger_balance'],
            'creditSummary' => $creditSummary,
            'creditStatuses' => CustomerCreditStatus::cases(),
            'paymentMethods' => PaymentMethod::cases(),
        ]);
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
        if ($customer->routeStops()->exists()
            || $customer->orders()->exists()
            || $customer->receivables()->exists()
            || $customer->accountEntries()->exists()) {
            return back()->with('error', 'No se puede eliminar un cliente con rutas, pedidos o historial financiero. Desactívalo para conservar la trazabilidad.');
        }

        $customer->delete();

        return redirect()->route('customers.index')->with('success', 'Cliente eliminado correctamente.');
    }
}
