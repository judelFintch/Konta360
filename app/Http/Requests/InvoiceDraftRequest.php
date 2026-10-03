<?php

namespace App\Http\Requests;

use App\Modules\Invoices\Enums\DeductionType;
use App\Modules\Payments\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $advance = DeductionType::Advance->value;

        return [
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'deductions' => ['nullable', 'array', 'max:20'],
            'deductions.*.type' => ['required', Rule::enum(DeductionType::class)],
            'deductions.*.description' => ['required', 'string', 'max:255'],
            'deductions.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999.999', 'decimal:0,3'],
            'deductions.*.unit_price' => ['required', 'numeric', 'gt:0', 'max:99999999999999.9999', 'decimal:0,4'],
            // An advance has necessarily been received, at the latest on the invoice date.
            'deductions.*.received_on' => ["required_if:deductions.*.type,{$advance}", 'nullable', 'date', 'before_or_equal:issue_date', 'before_or_equal:today'],
            'deductions.*.payment_method' => ["required_if:deductions.*.type,{$advance}", 'nullable', Rule::enum(PaymentMethod::class)],
            'deductions.*.treasury_account_id' => ['nullable', 'integer', 'exists:treasury_accounts,id'],
            'deductions.*.reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'deductions.*.description' => 'libellé',
            'deductions.*.quantity' => 'quantité',
            'deductions.*.unit_price' => 'prix unitaire',
            'deductions.*.received_on' => 'date de réception',
            'deductions.*.payment_method' => 'mode de paiement',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'notes' => $this->filled('notes') ? trim((string) $this->notes) : null,
            'deductions' => collect($this->input('deductions', []))
                ->filter(fn ($deduction) => is_array($deduction))
                ->map(fn (array $deduction) => [
                    ...$deduction,
                    'description' => trim((string) ($deduction['description'] ?? '')),
                    'reference' => filled($deduction['reference'] ?? null) ? trim((string) $deduction['reference']) : null,
                ])
                ->values()
                ->all(),
        ]);
    }
}
