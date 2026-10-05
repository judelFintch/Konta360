<?php

namespace App\Http\Requests;

use App\Modules\Companies\Validation\CompanyRule;
use App\Modules\Payments\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999.99'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'treasury_account_id' => ['nullable', 'integer', CompanyRule::exists('treasury_accounts')],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reference' => $this->filled('reference') ? trim((string) $this->reference) : null,
            'notes' => $this->filled('notes') ? trim((string) $this->notes) : null,
        ]);
    }
}
