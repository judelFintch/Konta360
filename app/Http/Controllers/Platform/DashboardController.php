<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PlatformEvent;
use App\Models\SubscriptionPayment;
use App\Modules\Billing\Enums\SubscriptionPaymentStatus;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Companies\Scopes\CompanyScope;
use Illuminate\View\View;

/**
 * Overview of the subscribers for Konta360 operators (ADR 0005). Business
 * figures only: no accounting data of any company.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $open = fn () => Company::query()->whereNull('closed_at');

        $byStatus = collect(SubscriptionStatus::cases())->mapWithKeys(fn (SubscriptionStatus $status) => [
            $status->value => $open()->withSubscriptionStatus($status)->count(),
        ]);

        // Monthly recurring revenue: the monthly price of every company with
        // paid days left, per currency.
        $recurring = $open()->withSubscriptionStatus(SubscriptionStatus::Active)
            ->join('plans', 'plans.id', '=', 'companies.plan_id')
            ->selectRaw('plans.currency, sum(plans.monthly_price) as total')
            ->groupBy('plans.currency')
            ->pluck('total', 'currency');

        $today = today()->toDateString();
        $inAWeek = today()->addDays(7)->toDateString();
        $payments = SubscriptionPayment::query()->withoutGlobalScope(CompanyScope::class);

        return view('platform.dashboard', [
            'byStatus' => $byStatus,
            'total' => $open()->count(),
            'suspended' => $open()->whereNotNull('suspended_at')->count(),
            'recurring' => $recurring,
            'collected' => (clone $payments)
                ->where('status', SubscriptionPaymentStatus::Confirmed)
                ->whereBetween('reviewed_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->selectRaw('currency, sum(amount) as total')
                ->groupBy('currency')
                ->pluck('total', 'currency'),
            'pendingPayments' => (clone $payments)->where('status', SubscriptionPaymentStatus::Pending)->count(),
            'signups' => Company::query()->where('created_at', '>=', now()->subDays(30))->count(),
            'closureRequests' => $open()->whereNotNull('closure_requested_at')->count(),
            'endingSoon' => $open()->with('plan')
                ->whereNull('suspended_at')
                ->where('billing_exempt', false)
                ->where(fn ($query) => $query
                    ->whereBetween('subscription_ends_at', [$today, $inAWeek])
                    ->orWhere(fn ($query) => $query->whereBetween('trial_ends_at', [$today, $inAWeek])
                        ->where(fn ($query) => $query->whereNull('subscription_ends_at')->orWhere('subscription_ends_at', '<', $today))))
                ->get()
                ->sortBy(fn (Company $company) => $company->accessEndsOn())
                ->values(),
            'latestSignups' => Company::query()->with('plan')->latest('id')->limit(5)->get(),
            'events' => PlatformEvent::query()->with(['actor', 'company'])->latest('id')->limit(10)->get(),
        ]);
    }
}
