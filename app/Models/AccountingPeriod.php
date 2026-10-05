<?php

namespace App\Models;

use App\Modules\Accounting\Enums\PeriodStatus;
use App\Modules\Companies\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'starts_on', 'ends_on', 'status', 'closed_at', 'closed_by'])]
class AccountingPeriod extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'status' => PeriodStatus::class,
            'closed_at' => 'datetime',
        ];
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function contains(string $date): bool
    {
        return $this->starts_on->lte($date) && $this->ends_on->gte($date);
    }
}
