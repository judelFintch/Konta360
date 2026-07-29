<?php

namespace App\Http\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'party_id' => [
                'required',
                Rule::exists('parties', 'id')->where(fn (Builder $query) => $query->where('is_active', true)),
            ],
            'issue_date' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:issue_date'],
            'currency' => ['required', Rule::in(['CDF', 'USD'])],
            'notes' => ['nullable', 'string', 'max:3000'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.catalog_item_id' => [
                'required',
                'distinct',
                Rule::exists('catalog_items', 'id')->where(fn (Builder $query) => $query->where('is_active', true)),
            ],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999.999'],
            'lines.*.discount_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $lines = collect($this->input('lines', []))
            ->filter(fn ($line) => filled($line['catalog_item_id'] ?? null))
            ->values()
            ->all();

        $this->merge([
            'notes' => $this->filled('notes') ? trim((string) $this->notes) : null,
            'lines' => $lines,
        ]);
    }
}
