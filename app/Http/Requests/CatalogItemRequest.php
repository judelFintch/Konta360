<?php

namespace App\Http\Requests;

use App\Modules\Catalog\Enums\ItemType;
use App\Modules\Catalog\Enums\Unit;
use App\Modules\Companies\Validation\CompanyRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function attributes(): array
    {
        return ['unit' => 'unité'];
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ItemType::class)],
            'sku' => [
                'required',
                'string',
                'max:80',
                CompanyRule::unique('catalog_items')->ignore($this->route('catalog_item')),
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
            // « Autre » in the list: the unit is the text typed next to it.
            'unit' => $this->input('unit') === Unit::OTHER
                ? trim((string) $this->input('unit_other'))
                : $this->input('unit'),
        ]);
    }
}
