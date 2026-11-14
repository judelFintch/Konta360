<?php

namespace App\Models;

use App\Modules\Companies\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'invoice_id', 'created_by', 'old_tax_total', 'new_tax_total',
    'old_total', 'new_total', 'line_changes', 'reason',
])]
class InvoiceTaxCorrection extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'old_tax_total' => 'decimal:2',
            'new_tax_total' => 'decimal:2',
            'old_total' => 'decimal:2',
            'new_total' => 'decimal:2',
            'line_changes' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
