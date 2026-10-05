<?php

namespace App\Models;

use App\Modules\Companies\Concerns\BelongsToCompany;
use App\Modules\FixedAssets\Enums\AssetCategory;
use App\Modules\FixedAssets\Enums\AssetStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code', 'name', 'category', 'description', 'acquisition_date', 'in_service_date',
    'acquisition_cost', 'residual_value', 'currency', 'useful_life_months', 'status',
    'supplier_id', 'created_by',
])]
class FixedAsset extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'category' => AssetCategory::class,
            'status' => AssetStatus::class,
            'acquisition_date' => 'date',
            'in_service_date' => 'date',
            'acquisition_cost' => 'decimal:2',
            'residual_value' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'supplier_id');
    }

    public function depreciableAmount(): float
    {
        return round((float) $this->acquisition_cost - (float) $this->residual_value, 2);
    }

    public function usefulLifeInYears(): float
    {
        return round($this->useful_life_months / 12, 2);
    }

    public function usefulLifeLabel(): string
    {
        $years = intdiv($this->useful_life_months, 12);
        $remainingMonths = $this->useful_life_months % 12;
        $parts = [];

        if ($years > 0) {
            $parts[] = $years.' '.($years === 1 ? 'an' : 'ans');
        }

        if ($remainingMonths > 0) {
            $parts[] = $remainingMonths.' mois';
        }

        return implode(' et ', $parts);
    }

    public function depreciations(): HasMany
    {
        return $this->hasMany(FixedAssetDepreciation::class);
    }
}
