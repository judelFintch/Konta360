<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Plan;
use App\Models\PlatformEvent;
use App\Models\User;
use App\Modules\Billing\Enums\SubscriptionPaymentMethod;
use App\Modules\Billing\Services\SubscriptionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Subscription and access actions taken by an operator on one subscriber
 * (ADR 0005). Each one is recorded in the platform log.
 */
class CompanySubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionManager $subscriptions) {}

    public function changePlan(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate(['plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('is_active', 1)]]);
        $from = $company->plan?->name ?? '—';
        $plan = Plan::findOrFail($data['plan_id']);

        $this->subscriptions->changePlan($company, $plan);
        PlatformEvent::record($request->user(), $company, 'plan_change', "Formule changée : {$from} → {$plan->name}");

        return back()->with('success', "« {$company->name} » est maintenant sur la formule {$plan->name}.");
    }

    public function extendTrial(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate(['days' => ['required', 'integer', 'min:1', 'max:365']]);

        $this->subscriptions->extendTrial($company, (int) $data['days']);
        PlatformEvent::record($request->user(), $company, 'trial_extension', "Évaluation prolongée de {$data['days']} jour(s), jusqu’au {$company->trial_ends_at->format('d/m/Y')}");

        return back()->with('success', "Évaluation prolongée jusqu’au {$company->trial_ends_at->format('d/m/Y')}.");
    }

    public function recordPayment(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('is_active', 1)->where('is_evaluation', 0)],
            'months' => ['required', 'integer', 'min:1', 'max:24'],
            'method' => ['required', Rule::enum(SubscriptionPaymentMethod::class)],
            'reference' => ['required', 'string', 'max:255'],
        ]);

        $payment = $this->subscriptions->recordReceivedPayment(
            $company,
            Plan::findOrFail($data['plan_id']),
            (int) $data['months'],
            SubscriptionPaymentMethod::from($data['method']),
            trim($data['reference']),
            $request->user(),
        );
        PlatformEvent::record($request->user(), $company, 'payment_recorded', "Paiement reçu enregistré : {$payment->amount} {$payment->currency}, {$payment->months} mois", [
            'payment_id' => $payment->id,
            'reference' => $payment->reference,
        ]);

        return back()->with('success', "Paiement enregistré : abonnement actif jusqu’au {$payment->period_ends_on->format('d/m/Y')}.");
    }

    /**
     * E.g. a compromised or abusive account. The company's own
     * administrators can reactivate it from their user management.
     */
    public function toggleUser(Request $request, Company $company, User $user): RedirectResponse
    {
        abort_unless($user->company_id === $company->id, 404);
        $user->forceFill(['is_active' => ! $user->is_active])->save();
        PlatformEvent::record($request->user(), $company, $user->is_active ? 'user_enabled' : 'user_disabled',
            ($user->is_active ? 'Utilisateur réactivé : ' : 'Utilisateur désactivé : ').$user->email);

        return back()->with('success', $user->is_active ? "{$user->email} est réactivé." : "{$user->email} est désactivé.");
    }
}
