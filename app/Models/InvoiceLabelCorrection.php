<?php

namespace App\Models;

use App\Modules\Companies\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['invoice_id', 'created_by', 'line_changes', 'reason'])]
class InvoiceLabelCorrection extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return ['line_changes' => 'array'];
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
