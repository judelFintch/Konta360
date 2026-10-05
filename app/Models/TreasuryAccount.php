<?php

namespace App\Models;

use App\Modules\Companies\Concerns\BelongsToCompany;
use App\Modules\Treasury\Enums\TreasuryAccountType;
use App\Modules\Treasury\Enums\TreasuryTransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'type', 'currency', 'opening_balance', 'is_active', 'created_by'])]
class TreasuryAccount extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'type' => TreasuryAccountType::class,
            'opening_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(TreasuryTransaction::class, 'treasury_account_id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(TreasuryTransaction::class, 'destination_account_id');
    }

    public function balance(): float
    {
        $inflows = (float) $this->transactions()->where('type', TreasuryTransactionType::Inflow)->sum('amount');
        $outflows = (float) $this->transactions()->where('type', TreasuryTransactionType::Outflow)->sum('amount');
        $outgoingTransfers = (float) $this->transactions()->where('type', TreasuryTransactionType::Transfer)->sum('amount');
        $incomingTransfers = (float) $this->incomingTransfers()->where('type', TreasuryTransactionType::Transfer)->sum('amount');

        return round((float) $this->opening_balance + $inflows + $incomingTransfers - $outflows - $outgoingTransfers, 2);
    }
}
