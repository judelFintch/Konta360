<?php

namespace App\Http\Requests;

use App\Modules\Companies\Validation\CompanyRule;
use App\Modules\Documents\Enums\DocumentLanguage;
use App\Modules\Parties\Enums\PartyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PartyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(PartyType::class)],
            'name' => ['required', 'string', 'max:255'],
            'tax_identifier' => [
                'nullable',
                'string',
                'max:255',
                CompanyRule::unique('parties')->ignore($this->route('party')),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:2000'],
            'document_language' => ['sometimes', 'required', Rule::enum(DocumentLanguage::class)],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'tax_identifier' => $this->filled('tax_identifier') ? $this->tax_identifier : null,
            'email' => $this->filled('email') ? $this->email : null,
            'phone' => $this->filled('phone') ? $this->phone : null,
            'address' => $this->filled('address') ? $this->address : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
