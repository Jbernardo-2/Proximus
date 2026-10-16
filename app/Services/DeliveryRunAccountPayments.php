<?php

namespace App\Services;

use App\CustomerAccountEntryStatus;
use App\CustomerAccountEntryType;
use App\Models\CustomerAccountEntry;
use App\Models\DeliveryRun;
use App\Models\Order;
use Illuminate\Support\Collection;

/**
 * Account payments (old debts) the driver collected during a run: by the run's driver,
 * from the run's customers, between leaving the warehouse and the settlement. The
 * driver hands that cash over together with the cash of the deliveries.
 */
class DeliveryRunAccountPayments
{
    /** @return Collection<int, CustomerAccountEntry> */
    public function for(DeliveryRun $run): Collection
    {
        if ($run->departed_at === null || $run->driver_id === null) {
            return collect();
        }

        return CustomerAccountEntry::query()
            ->with(['paymentLines', 'customer:id,code,business_name'])
            ->where('type', CustomerAccountEntryType::CustomerPayment)
            ->where('status', CustomerAccountEntryStatus::Active)
            ->where('created_by', $run->driver_id)
            ->whereIn('customer_id', Order::query()->select('customer_id')->whereIn('id', $run->runOrders()->select('order_id')))
            ->where('occurred_at', '>=', $run->departed_at)
            ->when($run->settled_at !== null, fn ($query) => $query->where('occurred_at', '<=', $run->settled_at))
            ->orderBy('occurred_at')
            ->get();
    }

    /**
     * @param  Collection<int, CustomerAccountEntry>  $entries
     * @return array<string, string> method => amount
     */
    public function byMethod(Collection $entries): array
    {
        $totals = [];

        foreach ($entries->flatMap->paymentLines as $line) {
            $totals[$line->method->value] = bcadd($totals[$line->method->value] ?? '0', (string) $line->amount, 4);
        }

        return $totals;
    }
}
