<?php

namespace App\Models;

use Database\Factories\CustomerReceivableAllocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_receivable_id', 'customer_account_entry_id', 'amount'])]
class CustomerReceivableAllocation extends Model
{
    /** @use HasFactory<CustomerReceivableAllocationFactory> */
    use HasFactory, HasUlids;

    public function receivable(): BelongsTo
    {
        return $this->belongsTo(CustomerReceivable::class, 'customer_receivable_id');
    }

    public function accountEntry(): BelongsTo
    {
        return $this->belongsTo(CustomerAccountEntry::class, 'customer_account_entry_id');
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:4'];
    }
}
