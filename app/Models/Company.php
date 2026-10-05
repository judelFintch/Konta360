<?php

namespace App\Models;

use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Companies\Services\CurrentCompany;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A subscribing organisation. Every business record belongs to exactly one
 * company and is only visible from inside that company (ADR 0002).
 */
#[Fillable([
    'name', 'legal_form', 'tax_identifier', 'trade_register', 'address', 'email',
    'phone', 'website', 'default_currency', 'invoice_footer', 'national_identifier',
    'cnss_number', 'representative_name', 'representative_title', 'bank_name',
    'bank_account_name', 'bank_account_number', 'bank_swift', 'mobile_money',
    'default_tax_rate', 'default_payment_days', 'default_quote_validity_days',
    'quote_prefix', 'invoice_prefix', 'credit_note_prefix', 'number_padding',
    'logo_path', 'signature_path', 'stamp_path',
])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    /**
     * Same defaults as the table, so that a company just created already
     * knows how to number its documents.
     */
    protected $attributes = [
        'default_currency' => 'CDF',
        'default_tax_rate' => 0,
        'default_payment_days' => 30,
        'default_quote_validity_days' => 30,
        'quote_prefix' => 'DEV',
        'invoice_prefix' => 'FAC',
        'credit_note_prefix' => 'AVO',
        'number_padding' => 5,
        'billing_exempt' => false,
    ];

    /**
     * The company the current request (or console task) works for.
     */
    public static function current(): self
    {
        return app(CurrentCompany::class)->get();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function termsAcceptor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'terms_accepted_by');
    }

    public function closureRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closure_requested_by');
    }

    /**
     * Paid days run to the end of subscription_ends_at; trial days to the
     * end of trial_ends_at (ADR 0003 § 1).
     */
    public function subscriptionStatus(): SubscriptionStatus
    {
        return match (true) {
            $this->billing_exempt => SubscriptionStatus::Exempt,
            $this->subscription_ends_at !== null && today()->lte($this->subscription_ends_at) => SubscriptionStatus::Active,
            $this->trial_ends_at !== null && today()->lte($this->trial_ends_at) => SubscriptionStatus::Trial,
            default => SubscriptionStatus::Expired,
        };
    }

    /**
     * Last day the company can work normally, or null when it has no end.
     */
    public function accessEndsOn(): ?Carbon
    {
        return match ($this->subscriptionStatus()) {
            SubscriptionStatus::Active => $this->subscription_ends_at,
            SubscriptionStatus::Trial => $this->trial_ends_at,
            default => null,
        };
    }

    public function hasAcceptedCurrentTerms(): bool
    {
        return $this->terms_version === config('konta360.terms_version');
    }

    public function isClosureRequested(): bool
    {
        return $this->closure_requested_at !== null && $this->closed_at === null;
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * Folder of the public disk holding this company's branding files.
     */
    public function storageDirectory(): string
    {
        return 'companies/'.$this->getKey();
    }

    protected function casts(): array
    {
        return [
            'default_tax_rate' => 'decimal:2',
            'default_payment_days' => 'integer',
            'default_quote_validity_days' => 'integer',
            'number_padding' => 'integer',
            'suspended_at' => 'datetime',
            'trial_ends_at' => 'date',
            'subscription_ends_at' => 'date',
            'billing_exempt' => 'boolean',
            'terms_accepted_at' => 'datetime',
            'closure_requested_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
