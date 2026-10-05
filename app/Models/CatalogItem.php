<?php

namespace App\Models;

use App\Modules\Catalog\Enums\ItemType;
use App\Modules\Companies\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'type',
    'sku',
    'name',
    'description',
    'unit',
    'unit_price',
    'currency',
    'tax_rate',
    'is_active',
])]
class CatalogItem extends Model
{
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'type' => ItemType::class,
            'unit_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
