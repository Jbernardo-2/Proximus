<?php

namespace App\Http\Requests;

use App\CustomerCreditStatus;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCustomerCreditProfileRequest extends OperationsRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->string('status')->trim()->toString(),
            'credit_limit' => trim((string) $this->input('credit_limit', '0')),
            'term_days' => $this->integer('term_days'),
            'grace_days' => $this->integer('grace_days'),
            'notes' => $this->nullableString('notes'),
            'reason' => $this->nullableString('reason'),
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
            'status' => ['required', Rule::enum(CustomerCreditStatus::class)],
            'credit_limit' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            'term_days' => ['required', 'integer', 'min:0', 'max:365'],
            'grace_days' => ['required', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('status') !== CustomerCreditStatus::Active->value) {
                return;
            }

            if (bccomp((string) $this->input('credit_limit'), '0', 4) <= 0) {
                $validator->errors()->add('credit_limit', 'Un crédito activo debe tener un límite mayor que cero.');
            }

            if ($this->integer('term_days') < 1) {
                $validator->errors()->add('term_days', 'Un crédito activo debe tener un plazo de al menos un día.');
            }
        }];
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
