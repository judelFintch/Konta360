<?php

namespace App\Modules\Companies\Concerns;

use App\Models\Company;
use App\Modules\Companies\Scopes\CompanyScope;
use App\Modules\Companies\Services\CurrentCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Confines a business model to the current company: every query is
 * filtered on company_id and every new record is stamped with it.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (self $model) {
            $model->company_id ??= app(CurrentCompany::class)->id();
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
