<?php

namespace App\Models;

use App\Modules\Billing\Enums\SubscriptionPaymentMethod;
use App\Modules\Billing\Enums\SubscriptionPaymentStatus;
use App\Modules\Companies\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment declared by a company for its subscription. It only extends the
 * subscription once a platform administrator has confirmed the money was
 * received (ADR 0003 § 2).
 */
#[Fillable(['plan_id', 'months', 'amount', 'currency', 'method', 'reference', 'submitted_by'])]
class SubscriptionPayment extends Model
{
    use BelongsToCompany;

    protected $attributes = [
        'status' => 'pending',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'months' => 'integer',
            'method' => SubscriptionPaymentMethod::class,
            'status' => SubscriptionPaymentStatus::class,
            'reviewed_at' => 'datetime',
            'period_starts_on' => 'date',
            'period_ends_on' => 'date',
        ];
    }
}
