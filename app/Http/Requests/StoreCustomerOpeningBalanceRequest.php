<?php

namespace App\Http\Requests;

class StoreCustomerOpeningBalanceRequest extends OperationsRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => trim((string) $this->input('amount', '')),
            'document_number' => $this->nullableString('document_number'),
            'notes' => $this->nullableString('notes'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'document_number' => ['nullable', 'string', 'max:80', 'unique:customer_receivables,document_number'],
            'issued_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:issued_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function ability(): string
    {
        return 'configure-customer-credit';
    }

    private function nullableString(string $key): ?string
    {
        $value = $this->string($key)->trim()->toString();

        return $value !== '' ? $value : null;
    }
}
