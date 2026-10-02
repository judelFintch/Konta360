<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name', 'legal_form', 'tax_identifier', 'trade_register', 'address', 'email',
    'phone', 'website', 'default_currency', 'invoice_footer', 'national_identifier',
    'cnss_number', 'representative_name', 'representative_title', 'bank_name',
    'bank_account_name', 'bank_account_number', 'bank_swift', 'mobile_money',
    'default_tax_rate', 'default_payment_days', 'default_quote_validity_days',
    'quote_prefix', 'invoice_prefix', 'credit_note_prefix', 'number_padding',
    'logo_path', 'signature_path', 'stamp_path',
])]
class CompanySetting extends Model
{
    public static function current(): self
    {
        return static::query()->first() ?? new static([
            'name' => config('app.name', 'Konta360'),
            'default_currency' => 'CDF',
            'default_tax_rate' => 0,
            'default_payment_days' => 30,
            'default_quote_validity_days' => 30,
            'quote_prefix' => 'DEV',
            'invoice_prefix' => 'FAC',
            'credit_note_prefix' => 'AVO',
            'number_padding' => 5,
        ]);
    }

    protected function casts(): array
    {
        return [
            'default_tax_rate' => 'decimal:2',
            'default_payment_days' => 'integer',
            'default_quote_validity_days' => 'integer',
            'number_padding' => 'integer',
        ];
    }

    public function documentNumber(string $type, int $id, mixed $date): string
    {
        $prefix = match ($type) {
            'quote' => $this->quote_prefix,
            'invoice' => $this->invoice_prefix,
            'credit_note' => $this->credit_note_prefix,
        };

        return sprintf(
            '%s-%s-%s',
            $prefix,
            $date->format('Y'),
            str_pad((string) $id, $this->number_padding, '0', STR_PAD_LEFT)
        );
    }
}
