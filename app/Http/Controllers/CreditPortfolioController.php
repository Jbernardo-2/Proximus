<?php

namespace App\Http\Controllers;

use App\CustomerReceivableStatus;
use App\Models\Customer;
use App\Models\CustomerReceivable;
use App\OrderStatus;
use App\PaymentTerm;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreditPortfolioController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('operations.credits.index', $this->portfolio($request));
    }

    /** @return array<string, mixed> */
    protected function portfolio(Request $request): array
    {
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        $overdueOnly = $request->boolean('overdue');
        $outstandingStatuses = [
            CustomerReceivableStatus::Open->value,
            CustomerReceivableStatus::PartiallyPaid->value,
        ];
        $committedStatuses = [
            OrderStatus::Confirmed->value,
            OrderStatus::Assigned->value,
            OrderStatus::Loaded->value,
            OrderStatus::InTransit->value,
        ];
        $customers = Customer::query()
            ->with('creditProfile')
            ->withSum([
                'receivables as outstanding_balance' => fn ($query) => $query
                    ->whereIn('status', $outstandingStatuses),
            ], 'outstanding_amount')
            ->withSum([
                'receivables as overdue_balance' => fn ($query) => $query
                    ->whereIn('status', $outstandingStatuses)
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '<', today()),
            ], 'outstanding_amount')
            ->withSum([
                'orders as committed_credit' => fn ($query) => $query
                    ->where('payment_term', PaymentTerm::Credit->value)
                    ->whereIn('status', $committedStatuses),
            ], 'total')
            ->when($search !== '', fn ($query) => $query->where(function ($customers) use ($search): void {
                $customers->where('business_name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%");
            }))
            ->when($status !== '', fn ($query) => $query->whereHas(
                'creditProfile',
                fn ($profiles) => $profiles->where('status', $status),
            ))
            ->when($overdueOnly, fn ($query) => $query->whereHas('receivables', fn ($receivables) => $receivables
                ->whereIn('status', $outstandingStatuses)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', today())))
            ->orderByDesc('overdue_balance')
            ->orderByDesc('outstanding_balance')
            ->orderBy('business_name')
            ->paginate(20)
            ->withQueryString();

        return [
            'customers' => $customers,
            'search' => $search,
            'selectedStatus' => $status,
            'overdueOnly' => $overdueOnly,
            'portfolioBalance' => (string) CustomerReceivable::query()
                ->whereIn('status', $outstandingStatuses)
                ->sum('outstanding_amount'),
            'overdueBalance' => (string) CustomerReceivable::query()
                ->whereIn('status', $outstandingStatuses)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', today())
                ->sum('outstanding_amount'),
            'debtorsCount' => CustomerReceivable::query()
                ->whereIn('status', $outstandingStatuses)
                ->distinct('customer_id')
                ->count('customer_id'),
        ];
    }
}
