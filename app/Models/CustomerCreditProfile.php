<?php

namespace App\Models;

use App\CustomerCreditStatus;
use Database\Factories\CustomerCreditProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'status', 'credit_limit', 'term_days', 'grace_days', 'notes', 'approved_at', 'approved_by', 'updated_by'])]
class CustomerCreditProfile extends Model
{
    /** @use HasFactory<CustomerCreditProfileFactory> */
    use HasFactory, HasUlids;

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected function casts(): array
    {
        return [
            'status' => CustomerCreditStatus::class,
            'credit_limit' => 'decimal:4',
            'term_days' => 'integer',
            'grace_days' => 'integer',
            'approved_at' => 'datetime',
        ];
    }
}
