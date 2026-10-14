<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\CreditPortfolioController as WebCreditPortfolioController;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditPortfolioController extends WebCreditPortfolioController
{
    public function index(Request $request): JsonResponse
    {
        $portfolio = $this->portfolio($request);
        $customers = $portfolio['customers'];

        return response()->json([
            'data' => collect($customers->items())->map(fn (Customer $customer): array => [
                'customer_id' => $customer->id,
                'code' => $customer->code,
                'business_name' => $customer->business_name,
                'status' => $customer->creditProfile?->status->value ?? 'disabled',
                'credit_limit' => $customer->creditProfile?->credit_limit ?? '0.0000',
                'outstanding_balance' => bcadd((string) ($customer->outstanding_balance ?? '0'), '0', 4),
                'overdue_balance' => bcadd((string) ($customer->overdue_balance ?? '0'), '0', 4),
                'committed_credit' => bcadd((string) ($customer->committed_credit ?? '0'), '0', 4),
            ])->all(),
            'totals' => [
                'portfolio_balance' => bcadd($portfolio['portfolioBalance'], '0', 4),
                'overdue_balance' => bcadd($portfolio['overdueBalance'], '0', 4),
                'debtors_count' => $portfolio['debtorsCount'],
            ],
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'total' => $customers->total(),
            ],
        ]);
    }
}
