<?php

namespace App\Models;

use App\Modules\Treasury\Enums\BankReconciliationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'number', 'treasury_account_id', 'starts_on', 'ends_on', 'statement_opening_balance',
    'statement_closing_balance', 'calculated_closing_balance', 'difference', 'currency',
    'status', 'notes', 'created_by', 'completed_at', 'completed_by',
])]
class BankReconciliation extends Model
{
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'statement_opening_balance' => 'decimal:2',
            'statement_closing_balance' => 'decimal:2',
            'calculated_closing_balance' => 'decimal:2',
            'difference' => 'decimal:2',
            'status' => BankReconciliationStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(TreasuryAccount::class, 'treasury_account_id');
    }

    public function transactions(): BelongsToMany
    {
        return $this->belongsToMany(TreasuryTransaction::class, 'bank_reconciliation_transactions');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
