<?php

namespace App\Models;

use App\Modules\Companies\Concerns\BelongsToCompany;
use App\Modules\Expenses\Enums\ExpenseStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'number', 'supplier_id', 'supplier_reference', 'expense_date', 'due_date', 'description',
    'currency', 'subtotal', 'tax_total', 'total', 'status', 'created_by', 'validated_at',
    'validated_by', 'cancelled_at', 'cancelled_by',
])]
class Expense extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'status' => ExpenseStatus::class,
            'validated_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'supplier_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ExpensePayment::class)->latest('payment_date')->latest('id');
    }

    public function paidAmount(): float
    {
        return round((float) $this->payments()->sum('amount'), 2);
    }

    public function balanceDue(): float
    {
        return max(0, round((float) $this->total - $this->paidAmount(), 2));
    }
}
