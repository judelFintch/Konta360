<?php

namespace App\Http\Middleware;

use App\Modules\Companies\Services\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Once its trial or paid period is over, a company keeps reading and
 * exporting its data but cannot create or change anything (ADR 0003 § 3).
 */
class EnsureWritableSubscription
{
    /**
     * Writes still allowed while expired: renewing, the company's own data
     * (export, closure, terms) and the user's session and profile.
     */
    private const ALWAYS_ALLOWED = ['subscription.*', 'administration.data.*', 'livewire.*', 'logout'];

    public function __construct(private readonly CurrentCompany $currentCompany) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || ! $this->currentCompany->has() || $request->routeIs(...self::ALWAYS_ALLOWED)) {
            return $next($request);
        }

        if ($this->currentCompany->get()->subscriptionStatus()->allowsWriting()) {
            return $next($request);
        }

        $message = 'Votre accès est en lecture seule : l’essai ou l’abonnement a expiré. Renouvelez votre abonnement pour continuer.';

        return $request->expectsJson()
            ? response()->json(['message' => $message], 402)
            : redirect()->route('subscription.show')->with('subscription_blocked', $message);
    }
}
