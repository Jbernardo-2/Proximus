<?php

namespace App\Models;

use App\CustomerCreditStatus;
use Database\Factories\CustomerCreditProfileHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'from_status', 'to_status', 'old_credit_limit', 'new_credit_limit', 'old_term_days', 'new_term_days', 'old_grace_days', 'new_grace_days', 'reason', 'changed_by', 'created_at'])]
class CustomerCreditProfileHistory extends Model
{
    /** @use HasFactory<CustomerCreditProfileHistoryFactory> */
    use HasFactory, HasUlids;

    public $timestamps = false;

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    protected function casts(): array
    {
        return [
            'from_status' => CustomerCreditStatus::class,
            'to_status' => CustomerCreditStatus::class,
            'old_credit_limit' => 'decimal:4',
            'new_credit_limit' => 'decimal:4',
            'old_term_days' => 'integer',
            'new_term_days' => 'integer',
            'old_grace_days' => 'integer',
            'new_grace_days' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
