<?php

namespace App\Modules\Billing\Services;

use App\Models\Company;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Modules\Billing\Enums\SubscriptionPaymentMethod;
use App\Modules\Billing\Enums\SubscriptionPaymentStatus;
use App\Modules\Companies\Scopes\CompanyScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Subscription life cycle (ADR 0003): free trial at sign-up, payments
 * declared by the company, then confirmed or rejected by the platform.
 */
class SubscriptionManager
{
    public function startTrial(Company $company, Plan $plan): void
    {
        $company->plan()->associate($plan);
        $company->trial_ends_at = today()->addDays(config('konta360.billing.trial_days') - 1);
        $company->save();
    }

    /**
     * Records a payment made outside the application. The amount is always
     * computed from the plan, never taken from the form.
     */
    public function declarePayment(Company $company, Plan $plan, int $months, SubscriptionPaymentMethod $method, string $reference, User $user): SubscriptionPayment
    {
        $payment = new SubscriptionPayment([
            'plan_id' => $plan->id,
            'months' => $months,
            'amount' => $plan->priceFor($months),
            'currency' => $plan->currency,
            'method' => $method,
            'reference' => $reference,
            'submitted_by' => $user->id,
        ]);
        $payment->company_id = $company->id;
        $payment->save();

        return $payment;
    }

    /**
     * The money was received: the paid period starts after whatever the
     * company already has (trial or paid days), so nothing is lost.
     */
    public function confirm(SubscriptionPayment $payment, User $reviewer): void
    {
        DB::transaction(function () use ($payment, $reviewer) {
            $payment = $this->lockPending($payment);
            $company = Company::query()->lockForUpdate()->findOrFail($payment->company_id);

            $startsOn = collect([
                today(),
                $company->subscription_ends_at?->copy()->addDay(),
                $company->trial_ends_at?->copy()->addDay(),
            ])->filter()->max();
            $endsOn = Carbon::parse($startsOn)->addMonthsNoOverflow($payment->months)->subDay();

            $payment->forceFill([
                'status' => SubscriptionPaymentStatus::Confirmed,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer->id,
                'period_starts_on' => $startsOn,
                'period_ends_on' => $endsOn,
            ])->save();

            $company->forceFill(['plan_id' => $payment->plan_id, 'subscription_ends_at' => $endsOn])->save();
        });
    }

    public function reject(SubscriptionPayment $payment, User $reviewer, string $reason): void
    {
        DB::transaction(function () use ($payment, $reviewer, $reason) {
            $this->lockPending($payment)->forceFill([
                'status' => SubscriptionPaymentStatus::Rejected,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer->id,
                'rejection_reason' => $reason,
            ])->save();
        });
    }

    /**
     * The platform moves a company to another plan without payment, e.g.
     * after a commercial agreement. Dates are unchanged.
     */
    public function changePlan(Company $company, Plan $plan): void
    {
        $company->plan()->associate($plan);
        $company->save();
    }

    /**
     * Adds days to the evaluation; an expired evaluation restarts today.
     */
    public function extendTrial(Company $company, int $days): void
    {
        $from = $company->trial_ends_at && $company->trial_ends_at->gte(today()) ? $company->trial_ends_at : today()->subDay();
        $company->trial_ends_at = $from->copy()->addDays($days);
        $company->save();
    }

    /**
     * Money received directly by Konta360 (cash, transfer seen on the
     * statement): recorded and confirmed in one step.
     */
    public function recordReceivedPayment(Company $company, Plan $plan, int $months, SubscriptionPaymentMethod $method, string $reference, User $operator): SubscriptionPayment
    {
        return DB::transaction(function () use ($company, $plan, $months, $method, $reference, $operator) {
            $payment = $this->declarePayment($company, $plan, $months, $method, $reference, $operator);
            $this->confirm($payment, $operator);

            return $payment->refresh();
        });
    }

    /**
     * Platform administrators have no current company: payments are read
     * across companies here, on purpose.
     */
    private function lockPending(SubscriptionPayment $payment): SubscriptionPayment
    {
        $payment = SubscriptionPayment::query()->withoutGlobalScope(CompanyScope::class)->lockForUpdate()->findOrFail($payment->id);

        if ($payment->status !== SubscriptionPaymentStatus::Pending) {
            throw ValidationException::withMessages(['payment' => 'Ce paiement a déjà été traité.']);
        }

        return $payment;
    }
}
