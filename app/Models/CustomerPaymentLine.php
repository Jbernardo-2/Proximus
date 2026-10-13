<?php

namespace App\Models;

use App\PaymentMethod;
use Database\Factories\CustomerPaymentLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_account_entry_id', 'method', 'amount', 'reference'])]
class CustomerPaymentLine extends Model
{
    /** @use HasFactory<CustomerPaymentLineFactory> */
    use HasFactory, HasUlids;

    public function accountEntry(): BelongsTo
    {
        return $this->belongsTo(CustomerAccountEntry::class, 'customer_account_entry_id');
    }

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'amount' => 'decimal:4',
        ];
    }
}
