<?php

namespace App\Actions;

use App\CustomerAccountEntryStatus;
use App\CustomerAccountEntryType;
use App\CustomerReceivableStatus;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\CustomerReceivable;
use App\Models\User;
use App\PaymentMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordCustomerPaymentAction
{
    public function __construct(private GenerateDeliveryNumberAction $generateNumber) {}

    /**
     * @param  array{amount: mixed, notes?: ?string, payment_lines: list<array{method: string, amount: mixed, reference?: ?string}>, allocations: list<array{receivable_id: string, amount: mixed}>}  $data
     */
    public function handle(Customer $customer, array $data, User $actor): CustomerAccountEntry
    {
        return DB::transaction(function () use ($customer, $data, $actor): CustomerAccountEntry {
            $lockedCustomer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $paymentTotal = '0.0000';

            foreach ($data['payment_lines'] as $index => $line) {
                $method = PaymentMethod::from($line['method']);

                if ($method->requiresReference() && blank($line['reference'] ?? null)) {
                    throw ValidationException::withMessages([
                        "payment_lines.{$index}.reference" => ["La referencia es obligatoria para pagos por {$method->label()}."],
                    ]);
                }

                $paymentTotal = bcadd($paymentTotal, (string) $line['amount'], 4);
            }

            if (bccomp($paymentTotal, (string) $data['amount'], 4) !== 0) {
                throw ValidationException::withMessages([
                    'payment_lines' => ['Los métodos de pago deben sumar exactamente el total recibido.'],
                ]);
            }

            $receivableIds = collect($data['allocations'])->pluck('receivable_id');
            $receivables = CustomerReceivable::query()
                ->where('customer_id', $lockedCustomer->id)
                ->whereIn('id', $receivableIds)
                ->with('order:id,order_number')
                ->orderBy('opened_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($receivables->count() !== $receivableIds->unique()->count()) {
                throw ValidationException::withMessages([
                    'allocations' => ['Una de las deudas no pertenece al cliente o ya no está disponible.'],
                ]);
            }

            $allocatedTotal = '0.0000';

            foreach ($data['allocations'] as $index => $allocation) {
                $receivable = $receivables->get($allocation['receivable_id']);
                $amount = (string) $allocation['amount'];

                if (! $receivable->status->isOutstanding()
                    || bccomp($amount, '0', 4) <= 0
                    || bccomp($amount, (string) $receivable->outstanding_amount, 4) > 0) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.amount" => ['El monto aplicado supera el saldo vigente de la deuda.'],
                    ]);
                }

                $allocatedTotal = bcadd($allocatedTotal, $amount, 4);
            }

            if (bccomp($allocatedTotal, (string) $data['amount'], 4) !== 0) {
                throw ValidationException::withMessages([
                    'allocations' => ['La aplicación a deudas debe sumar exactamente el abono.'],
                ]);
            }

            $balanceBefore = bcadd((string) CustomerReceivable::query()
                ->where('customer_id', $lockedCustomer->id)
                ->outstanding()
                ->sum('outstanding_amount'), '0', 4);

            $receivedAt = now();
            $singlePaymentLine = count($data['payment_lines']) === 1 ? $data['payment_lines'][0] : null;
            $entry = $lockedCustomer->accountEntries()->create([
                'type' => CustomerAccountEntryType::CustomerPayment,
                'status' => CustomerAccountEntryStatus::Active,
                'receipt_number' => $this->generateNumber->handle('customer_payment', 'ABO', $receivedAt, $data['receipt_number'] ?? null),
                'method' => $singlePaymentLine === null
                    ? null
                    : PaymentMethod::from($singlePaymentLine['method']),
                'debit_amount' => '0.0000',
                'credit_amount' => $allocatedTotal,
                'currency' => config('proximus.currency', 'HNL'),
                'reference' => $singlePaymentLine['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'occurred_at' => $receivedAt,
                'created_by' => $actor->id,
            ]);

            foreach ($data['payment_lines'] as $line) {
                $entry->paymentLines()->create([
                    'method' => PaymentMethod::from($line['method']),
                    'amount' => $line['amount'],
                    'reference' => $line['reference'] ?? null,
                ]);
            }

            $allocationSnapshots = [];

            foreach ($data['allocations'] as $allocation) {
                $receivable = $receivables->get($allocation['receivable_id']);
                $amount = (string) $allocation['amount'];
                $outstandingBefore = (string) $receivable->outstanding_amount;
                $outstanding = bcsub((string) $receivable->outstanding_amount, $amount, 4);
                $entry->allocations()->create([
                    'customer_receivable_id' => $receivable->id,
                    'amount' => $amount,
                ]);
                $receivable->forceFill([
                    'outstanding_amount' => $outstanding,
                    'status' => bccomp($outstanding, '0', 4) === 0
                        ? CustomerReceivableStatus::Paid
                        : CustomerReceivableStatus::PartiallyPaid,
                    'paid_at' => bccomp($outstanding, '0', 4) === 0 ? $receivedAt : null,
                ])->save();

                $allocationSnapshots[] = [
                    'receivable_id' => $receivable->id,
                    'document_number' => $receivable->order?->order_number ?: $receivable->document_number,
                    'source' => $receivable->source->value,
                    'source_label' => $receivable->source->label(),
                    'original_amount' => (string) $receivable->original_amount,
                    'balance_before' => $outstandingBefore,
                    'amount' => $amount,
                    'balance_after' => $outstanding,
                    'due_date' => $receivable->due_date?->toDateString(),
                ];
            }

            $companyProfile = CompanyProfile::query()->findOrFail(CompanyProfile::SINGLETON_ID);
            $entry->forceFill([
                'receipt_snapshot' => [
                    'version' => 1,
                    'company' => [
                        'commercial_name' => $companyProfile->commercial_name,
                        'legal_name' => $companyProfile->legal_name,
                        'rtn' => $companyProfile->rtn,
                        'phone' => $companyProfile->phone,
                        'whatsapp' => $companyProfile->whatsapp,
                        'email' => $companyProfile->email,
                        'address' => $companyProfile->address,
                        'slogan' => $companyProfile->slogan,
                    ],
                    'customer' => [
                        'code' => $lockedCustomer->code,
                        'business_name' => $lockedCustomer->business_name,
                        'contact_name' => $lockedCustomer->contact_name,
                        'phone' => $lockedCustomer->phone,
                        'whatsapp' => $lockedCustomer->whatsapp,
                        'address' => $lockedCustomer->address,
                    ],
                    'payment' => [
                        'receipt_number' => $entry->receipt_number,
                        'occurred_at' => $receivedAt->toIso8601String(),
                        'currency' => $entry->currency,
                        'amount' => $allocatedTotal,
                        'notes' => $entry->notes,
                        'received_by' => $actor->name,
                        'methods' => collect($data['payment_lines'])->map(function (array $line): array {
                            $method = PaymentMethod::from($line['method']);

                            return [
                                'method' => $method->value,
                                'label' => $method->label(),
                                'amount' => (string) $line['amount'],
                                'reference' => $line['reference'] ?? null,
                            ];
                        })->all(),
                    ],
                    'balances' => [
                        'before' => $balanceBefore,
                        'after' => bcsub($balanceBefore, $allocatedTotal, 4),
                    ],
                    'allocations' => $allocationSnapshots,
                ],
            ])->save();

            return $entry->load(['customer', 'paymentLines', 'allocations.receivable.order', 'createdBy']);
        }, 3);
    }
}
