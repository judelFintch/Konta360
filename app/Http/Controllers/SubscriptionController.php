<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Modules\Administration\Enums\Permission;
use App\Modules\Billing\Enums\SubscriptionPaymentMethod;
use App\Modules\Billing\Services\PlanLimits;
use App\Modules\Billing\Services\SubscriptionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The company's subscription: status, usage of the plan's limits, payment
 * instructions and payments declared for confirmation (ADR 0003).
 */
class SubscriptionController extends Controller
{
    public const DURATIONS = [1, 3, 6, 12];

    public function show(PlanLimits $limits): View
    {
        $company = Company::current()->load('plan');

        return view('subscription.show', [
            'company' => $company,
            'status' => $company->subscriptionStatus(),
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'payments' => SubscriptionPayment::query()->with(['plan', 'submitter'])->latest('id')->get(),
            'usage' => [
                'users' => [$limits->activeUsers($company), $limits->limit($company, 'max_users')],
                'invoices' => [$limits->invoicesValidatedThisMonth(), $limits->limit($company, 'max_invoices_per_month')],
            ],
            'durations' => self::DURATIONS,
            'instructions' => array_filter(config('konta360.billing.payment_instructions')),
        ]);
    }

    public function storePayment(Request $request, SubscriptionManager $subscriptions): RedirectResponse
    {
        abort_unless($request->user()->can(Permission::SettingsManage->value), 403);
        $data = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('is_active', true)],
            'months' => ['required', 'integer', Rule::in(self::DURATIONS)],
            'method' => ['required', Rule::enum(SubscriptionPaymentMethod::class)],
            'reference' => ['required', 'string', 'max:255'],
        ]);

        $payment = $subscriptions->declarePayment(
            Company::current(),
            Plan::findOrFail($data['plan_id']),
            (int) $data['months'],
            SubscriptionPaymentMethod::from($data['method']),
            trim($data['reference']),
            $request->user(),
        );

        return to_route('subscription.show')->with('success', "Paiement de {$payment->amount} {$payment->currency} déclaré. Votre abonnement sera prolongé dès sa vérification par l’équipe Konta360.");
    }
}
