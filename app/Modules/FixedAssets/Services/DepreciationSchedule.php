<?php

namespace App\Modules\FixedAssets\Services;

use App\Models\FixedAsset;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DepreciationSchedule
{
    /**
     * @return Collection<int, array{period: Carbon, depreciation: float, accumulated: float, net_book_value: float}>
     */
    public function for(FixedAsset $asset): Collection
    {
        $base = $asset->depreciableAmount();
        $months = $asset->useful_life_months;
        $monthly = round($base / $months, 2);
        $accumulated = 0.0;

        return collect(range(1, $months))->map(function (int $index) use ($asset, $base, $monthly, $months, &$accumulated) {
            $depreciation = $index === $months ? round($base - $accumulated, 2) : $monthly;
            $accumulated = round($accumulated + $depreciation, 2);

            return [
                'period' => $asset->in_service_date->copy()->addMonthsNoOverflow($index)->endOfMonth(),
                'depreciation' => $depreciation,
                'accumulated' => $accumulated,
                'net_book_value' => round((float) $asset->acquisition_cost - $accumulated, 2),
            ];
        });
    }
}
