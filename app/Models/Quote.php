<?php

namespace App\Models;

use App\Modules\Companies\Concerns\BelongsToCompany;
use App\Modules\Quotes\Enums\QuoteStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'number',
    'party_id',
    'status',
    'issue_date',
    'valid_until',
    'currency',
    'notes',
    'subtotal',
    'discount_total',
    'tax_total',
    'total',
    'created_by',
])]
class Quote extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'issue_date' => 'date',
            'valid_until' => 'date',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class)->orderBy('position');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * A quote still awaiting an answer after its validity date.
     */
    public function isExpired(): bool
    {
        return in_array($this->status, [QuoteStatus::Draft, QuoteStatus::Sent], true)
            && $this->valid_until->lt(today());
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }
}
