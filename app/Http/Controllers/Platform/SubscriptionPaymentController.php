<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformEvent;
use App\Models\SubscriptionPayment;
use App\Modules\Billing\Enums\SubscriptionPaymentStatus;
use App\Modules\Billing\Services\SubscriptionManager;
use App\Modules\Companies\Scopes\CompanyScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Payments declared by companies, checked against the mobile money or bank
 * statement before they extend a subscription. Platform administrators work
 * across companies, hence the explicit removal of the company scope.
 */
class SubscriptionPaymentController extends Controller
{
    public function index(Request $request): View
    {
        $status = SubscriptionPaymentStatus::tryFrom((string) $request->query('status')) ?? SubscriptionPaymentStatus::Pending;
        $payments = SubscriptionPayment::query()->withoutGlobalScope(CompanyScope::class)
            ->with(['company', 'plan', 'submitter', 'reviewer'])
            ->where('status', $status)
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('platform.payments.index', compact('payments', 'status'));
    }

    public function confirm(Request $request, int $payment, SubscriptionManager $subscriptions): RedirectResponse
    {
        $payment = $this->find($payment);
        $subscriptions->confirm($payment, $request->user());
        PlatformEvent::record($request->user(), $payment->company, 'payment_confirmed', "Paiement confirmé : {$payment->amount} {$payment->currency}, {$payment->months} mois", ['payment_id' => $payment->id]);

        return back()->with('success', 'Paiement confirmé : l’abonnement de la société est prolongé.');
    }

    public function reject(Request $request, int $payment, SubscriptionManager $subscriptions): RedirectResponse
    {
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'min:3', 'max:255']]);
        $payment = $this->find($payment);
        $subscriptions->reject($payment, $request->user(), $data['rejection_reason']);
        PlatformEvent::record($request->user(), $payment->company, 'payment_rejected', "Paiement refusé : {$data['rejection_reason']}", ['payment_id' => $payment->id]);

        return back()->with('success', 'Paiement refusé.');
    }

    private function find(int $payment): SubscriptionPayment
    {
        return SubscriptionPayment::query()->withoutGlobalScope(CompanyScope::class)->findOrFail($payment);
    }
}
