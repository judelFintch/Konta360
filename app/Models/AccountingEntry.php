<?php

namespace App\Models;

use App\Modules\Accounting\Enums\EntryStatus;
use App\Modules\Companies\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'number', 'journal_id', 'entry_date', 'label', 'currency', 'status',
    'source_type', 'source_id', 'created_by', 'posted_at', 'posted_by',
])]
class AccountingEntry extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'status' => EntryStatus::class,
            'posted_at' => 'datetime',
        ];
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AccountingEntryLine::class)->orderBy('position');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function totalDebit(): float
    {
        return round((float) $this->lines()->sum('debit'), 2);
    }

    public function totalCredit(): float
    {
        return round((float) $this->lines()->sum('credit'), 2);
    }
}
