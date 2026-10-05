<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A subscription plan. Plans are shared by every company and edited from the
 * platform area only. The evaluation plan is the free first month of every
 * new company; it cannot be bought.
 */
#[Fillable([
    'name', 'description', 'monthly_price', 'currency', 'max_users',
    'max_invoices_per_month', 'is_active', 'sort_order',
])]
class Plan extends Model
{
    public static function evaluation(): self
    {
        return static::query()->where('is_evaluation', true)->firstOrFail();
    }

    /**
     * Plans a company can pay for: active, and not the free evaluation.
     */
    #[Scope]
    protected function purchasable(Builder $query): void
    {
        $query->where('is_active', true)->where('is_evaluation', false)->orderBy('sort_order');
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    public function priceFor(int $months): string
    {
        return number_format((float) $this->monthly_price * $months, 2, '.', '');
    }

    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'max_users' => 'integer',
            'max_invoices_per_month' => 'integer',
            'is_evaluation' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
