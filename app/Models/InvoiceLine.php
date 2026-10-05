<?php

namespace App\Models;

use App\Modules\Companies\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'catalog_item_id',
    'position',
    'sku',
    'description',
    'unit',
    'quantity',
    'unit_price',
    'discount_rate',
    'tax_rate',
    'subtotal',
    'discount_amount',
    'tax_amount',
    'total',
])]
class InvoiceLine extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'discount_rate' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creditNoteLines(): HasMany
    {
        return $this->hasMany(CreditNoteLine::class);
    }

    public function creditedQuantity(): float
    {
        return round((float) $this->creditNoteLines()->sum('quantity'), 3);
    }

    public function creditableQuantity(): float
    {
        return max(0, round((float) $this->quantity - $this->creditedQuantity(), 3));
    }
}
