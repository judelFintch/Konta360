<?php

namespace App\Models;

use App\Modules\Invoices\Enums\DeductionType;
use App\Modules\Payments\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'position',
    'type',
    'description',
    'quantity',
    'unit_price',
    'amount',
    'received_on',
    'payment_method',
    'treasury_account_id',
    'reference',
    'payment_id',
])]
class InvoiceDeduction extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'type' => DeductionType::class,
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'amount' => 'decimal:2',
            'received_on' => 'date',
            'payment_method' => PaymentMethod::class,
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function treasuryAccount(): BelongsTo
    {
        return $this->belongsTo(TreasuryAccount::class);
    }

    public function isAdvance(): bool
    {
        return $this->type === DeductionType::Advance;
    }
}
