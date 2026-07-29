<?php

namespace App\Http\Requests;

use App\Modules\FixedAssets\Enums\AssetCategory;
use App\Modules\FixedAssets\Enums\AssetStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FixedAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(AssetCategory::class)],
            'description' => ['nullable', 'string', 'max:3000'],
            'acquisition_date' => ['required', 'date'],
            'in_service_date' => ['required', 'date', 'after_or_equal:acquisition_date'],
            'acquisition_cost' => ['required', 'numeric', 'gt:0', 'max:9999999999999999.99'],
            'residual_value' => ['required', 'numeric', 'min:0', 'lt:acquisition_cost'],
            'currency' => ['required', 'in:CDF,USD'],
            'useful_life_months' => ['required', 'integer', 'min:1', 'max:1200'],
            'supplier_id' => ['nullable', 'exists:parties,id'],
            'status' => ['sometimes', Rule::enum(AssetStatus::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'description' => $this->filled('description') ? trim((string) $this->description) : null,
            'supplier_id' => $this->filled('supplier_id') ? $this->supplier_id : null,
            'residual_value' => $this->filled('residual_value') ? $this->residual_value : 0,
        ]);
    }
}
