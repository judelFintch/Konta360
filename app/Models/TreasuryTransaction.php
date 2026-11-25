<?php

namespace App\Models;

use App\Modules\Companies\Concerns\BelongsToCompany;
use App\Modules\Treasury\Enums\TreasuryTransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'number', 'treasury_account_id', 'destination_account_id', 'type', 'transaction_date',
    'amount', 'currency', 'description', 'reference', 'source_type', 'source_id', 'created_by',
])]
class TreasuryTransaction extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'type' => TreasuryTransactionType::class,
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(TreasuryAccount::class, 'treasury_account_id');
    }

    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(TreasuryAccount::class, 'destination_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reconciliations(): BelongsToMany
    {
        return $this->belongsToMany(BankReconciliation::class, 'bank_reconciliation_transactions');
    }

    /**
     * Movements that change the balance of $account: its own entries and
     * outflows, plus the transfers it receives.
     */
    #[Scope]
    protected function forAccount(Builder $query, TreasuryAccount $account): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('treasury_account_id', $account->id)
            ->orWhere(fn (Builder $query) => $query
                ->where('destination_account_id', $account->id)
                ->where('type', TreasuryTransactionType::Transfer)));
    }

    public function signedAmountFor(TreasuryAccount $account): float
    {
        if ($this->type === TreasuryTransactionType::Inflow && $this->treasury_account_id === $account->id) {
            return (float) $this->amount;
        }

        if ($this->type === TreasuryTransactionType::Transfer && $this->destination_account_id === $account->id) {
            return (float) $this->amount;
        }

        return -1 * (float) $this->amount;
    }
}
