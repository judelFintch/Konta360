<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
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
        $subscriptions->confirm($this->find($payment), $request->user());

        return back()->with('success', 'Paiement confirmé : l’abonnement de la société est prolongé.');
    }

    public function reject(Request $request, int $payment, SubscriptionManager $subscriptions): RedirectResponse
    {
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'min:3', 'max:255']]);
        $subscriptions->reject($this->find($payment), $request->user(), $data['rejection_reason']);

        return back()->with('success', 'Paiement refusé.');
    }

    private function find(int $payment): SubscriptionPayment
    {
        return SubscriptionPayment::query()->withoutGlobalScope(CompanyScope::class)->findOrFail($payment);
    }
}
