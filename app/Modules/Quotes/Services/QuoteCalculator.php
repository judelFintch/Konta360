<?php

namespace App\Modules\Quotes\Services;

use App\Models\CatalogItem;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class QuoteCalculator
{
    /**
     * @param  list<array{catalog_item_id: int|string, quantity: int|float|string, discount_rate: int|float|string}>  $inputLines
     * @return array{lines: list<array<string, mixed>>, subtotal: float, discount_total: float, tax_total: float, total: float}
     */
    public function calculate(array $inputLines, string $currency): array
    {
        $items = CatalogItem::query()
            ->whereIn('id', collect($inputLines)->pluck('catalog_item_id'))
            ->get()
            ->keyBy('id');

        $lines = collect($inputLines)->map(function (array $input, int $index) use ($items, $currency) {
            /** @var CatalogItem $item */
            $item = $items->get((int) $input['catalog_item_id']);

            if ($item->currency !== $currency) {
                throw ValidationException::withMessages([
                    "lines.{$index}.catalog_item_id" => "La devise de l’article {$item->name} ne correspond pas à celle du devis.",
                ]);
            }

            $quantity = (float) $input['quantity'];
            $discountRate = (float) $input['discount_rate'];
            $unitPrice = (float) $item->unit_price;
            $taxRate = (float) $item->tax_rate;
            $subtotal = round($quantity * $unitPrice, 2);
            $discountAmount = round($subtotal * $discountRate / 100, 2);
            $taxableAmount = $subtotal - $discountAmount;
            $taxAmount = round($taxableAmount * $taxRate / 100, 2);

            return [
                'catalog_item_id' => $item->id,
                'position' => $index + 1,
                'sku' => $item->sku,
                'description' => $item->name,
                'unit' => $item->unit,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_rate' => $discountRate,
                'tax_rate' => $taxRate,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'total' => round($taxableAmount + $taxAmount, 2),
            ];
        });

        return [
            'lines' => $lines->all(),
            'subtotal' => $this->sum($lines, 'subtotal'),
            'discount_total' => $this->sum($lines, 'discount_amount'),
            'tax_total' => $this->sum($lines, 'tax_amount'),
            'total' => $this->sum($lines, 'total'),
        ];
    }

    private function sum(Collection $lines, string $column): float
    {
        return round((float) $lines->sum($column), 2);
    }
}
