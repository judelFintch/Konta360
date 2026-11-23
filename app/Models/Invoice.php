<?php

namespace App\Models;

use App\Modules\Companies\Concerns\BelongsToCompany;
use App\Modules\Documents\Enums\DocumentLanguage;
use App\Modules\Invoices\Enums\DeductionType;
use App\Modules\Invoices\Enums\InvoiceStatus;
use App\Modules\Payments\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'number',
    'quote_id',
    'party_id',
    'status',
    'issue_date',
    'due_date',
    'currency',
    'language',
    'notes',
    'subtotal',
    'discount_total',
    'tax_total',
    'total',
    'deductions_total',
    'validated_at',
    'validated_by',
    'cancelled_at',
    'cancelled_by',
    'created_by',
])]
class Invoice extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'language' => DocumentLanguage::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'deductions_total' => 'decimal:2',
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

    public function deductions(): HasMany
    {
        return $this->hasMany(InvoiceDeduction::class)->orderBy('position');
    }

    /**
     * What the customer still has to pay according to the document itself:
     * total including tax minus the advance and the costs they bore.
     */
    public function netPayable(): float
    {
        return round((float) $this->total - (float) $this->deductions_total, 2);
    }

    /**
     * Costs borne by the customer, offset against the receivable. Advances are
     * not included: once the invoice is validated they are real payments.
     */
    public function offsetAmount(): float
    {
        // Spares a query per invoice in lists: most invoices have no deduction.
        if ((float) $this->deductions_total <= 0) {
            return 0.0;
        }

        $deductions = $this->relationLoaded('deductions') ? $this->deductions : $this->deductions()->get();

        return round((float) $deductions->where('type', DeductionType::ClientExpense)->sum('amount'), 2);
    }

    /**
     * Part of the recorded payments that comes from advances deducted on
     * the document, so that it is not shown twice.
     */
    public function advancePaidAmount(): float
    {
        if ((float) $this->deductions_total <= 0) {
            return 0.0;
        }

        $paymentIds = $this->deductions()->whereNotNull('payment_id')->pluck('payment_id');

        return round((float) $this->recordedPayments()->whereIn('id', $paymentIds)->sum('amount'), 2);
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

    public function deductionCorrections(): HasMany
    {
        return $this->hasMany(InvoiceDeductionCorrection::class)->latest();
    }

    public function labelCorrections(): HasMany
    {
        return $this->hasMany(InvoiceLabelCorrection::class)->latest();
    }

    public function taxCorrections(): HasMany
    {
        return $this->hasMany(InvoiceTaxCorrection::class)->latest();
    }

    public function taxCorrectionBlocker(): ?string
    {
        return match (true) {
            $this->status !== InvoiceStatus::Validated => 'Seule une facture validée peut faire l’objet d’une correction de TVA.',
            $this->recordedPayments()->exists() => 'Cette facture comporte un règlement actif : sa TVA ne peut plus être corrigée directement.',
            $this->creditNotes()->exists() => 'Un avoir existe déjà sur cette facture : sa TVA ne peut plus être corrigée directement.',
            default => null,
        };
    }

    public function creditedAmount(): float
    {
        $creditNotes = $this->relationLoaded('creditNotes') ? $this->creditNotes : $this->creditNotes();

        return round((float) $creditNotes->sum('total'), 2);
    }

    public function paidAmount(): float
    {
        $payments = $this->relationLoaded('recordedPayments') ? $this->recordedPayments : $this->recordedPayments();

        return round((float) $payments->sum('amount'), 2);
    }

    /**
     * Why the invoice cannot be cancelled, or null when it can.
     */
    public function cancellationBlocker(): ?string
    {
        return match (true) {
            $this->status === InvoiceStatus::Cancelled => 'Cette facture est déjà annulée.',
            $this->recordedPayments()->exists() => 'Des règlements sont enregistrés sur cette facture : contrepassez-les d’abord, ou émettez un avoir.',
            $this->creditNotes()->exists() => 'Un avoir a déjà été émis sur cette facture : complétez la correction par un avoir plutôt que par une annulation.',
            default => null,
        };
    }

    /**
     * Invoice numbers must follow the issue dates: an invoice cannot be
     * numbered with a future date, nor with a date earlier than an invoice
     * already numbered. Null when it can be validated.
     */
    public function chronologyBlocker(): ?string
    {
        if ($this->issue_date->gt(today())) {
            return 'La date de facture ne peut pas être dans le futur : corrigez-la avant de valider.';
        }

        $latest = static::query()
            ->whereNotNull('number')
            ->whereKeyNot($this->getKey())
            ->max('issue_date');

        return $latest && $this->issue_date->lt($latest)
            ? 'La facture est datée avant la dernière facture numérotée (du '.Carbon::parse($latest)->format('d/m/Y').') : la numérotation doit suivre l’ordre des dates. Corrigez la date de la facture.'
            : null;
    }

    public function isOverdue(): bool
    {
        return $this->status === InvoiceStatus::Validated
            && $this->due_date->lt(today())
            && $this->balanceDue() > 0;
    }

    public function balanceDue(): float
    {
        return max(0, round((float) $this->total - $this->offsetAmount() - $this->creditedAmount() - $this->paidAmount(), 2));
    }

    /**
     * A validated invoice whose whole amount was cancelled by credit notes:
     * it stays « validated » in the books but is no longer owed.
     */
    public function isFullyCredited(): bool
    {
        return $this->status === InvoiceStatus::Validated
            && $this->creditedAmount() > 0
            && $this->creditedAmount() >= round((float) $this->total, 2);
    }

    public function paymentLabel(): string
    {
        $paid = $this->paidAmount();

        return match (true) {
            $this->isFullyCredited() => 'Annulée par avoir',
            $paid <= 0 && $this->balanceDue() <= 0 && $this->creditedAmount() > 0 => 'Soldée par avoir',
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
