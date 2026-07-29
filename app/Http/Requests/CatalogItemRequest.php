<?php

namespace App\Http\Requests;

use App\Modules\Catalog\Enums\ItemType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ItemType::class)],
            'sku' => [
                'required',
                'string',
                'max:80',
                Rule::unique('catalog_items')->ignore($this->route('catalog_item')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'unit' => ['required', 'string', 'max:30'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'currency' => ['required', Rule::in(['CDF', 'USD'])],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sku' => mb_strtoupper(trim((string) $this->sku)),
            'description' => $this->filled('description') ? trim((string) $this->description) : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
