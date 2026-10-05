<?php

namespace App\Models;

use App\Modules\Companies\Services\CurrentCompany;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        ];
    }
}
