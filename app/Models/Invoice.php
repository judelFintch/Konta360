<?php

namespace App\Models;

use App\Modules\Invoices\Enums\InvoiceStatus;
use App\Modules\Payments\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'number',
    'quote_id',
    'party_id',
    'status',
    'issue_date',
    'due_date',
    'currency',
    'notes',
    'subtotal',
    'discount_total',
    'tax_total',
    'total',
    'validated_at',
    'validated_by',
    'cancelled_at',
    'cancelled_by',
    'created_by',
])]
class Invoice extends Model
{
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'validated_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('position');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('payment_date')->latest('id');
    }

    public function recordedPayments(): HasMany
    {
        return $this->payments()->where('status', PaymentStatus::Recorded);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class)->latest('issue_date')->latest('id');
    }

    public function creditedAmount(): float
    {
        return round((float) $this->creditNotes()->sum('total'), 2);
    }

    public function paidAmount(): float
    {
        return round((float) $this->recordedPayments()->sum('amount'), 2);
    }

    public function balanceDue(): float
    {
        return max(0, round((float) $this->total - $this->creditedAmount() - $this->paidAmount(), 2));
    }

    public function paymentLabel(): string
    {
        $paid = $this->paidAmount();

        return match (true) {
            $paid <= 0 => 'Non payée',
            $this->balanceDue() <= 0 => 'Payée',
            default => 'Partiellement payée',
        };
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
