<?php

namespace App\Http\Middleware;

use App\Modules\Companies\Services\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes the authenticated user's company the current company, so that every
 * business query of the request is confined to it.
 */
class SetCurrentCompany
{
    public function __construct(private readonly CurrentCompany $currentCompany) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $company = $user->company;

        if (! $company) {
            // Platform administrators belong to no company and only reach
            // the platform area.
            if ($user->is_platform_admin) {
                return $request->routeIs('platform.*', 'verification.*', 'password.*')
                    ? $next($request)
                    : redirect()->route('platform.companies.index');
            }

            abort(403, 'Ce compte n’est rattaché à aucune société.');
        }

        if ($company->isSuspended()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['form.email' => 'L’accès de votre société est suspendu. Contactez le support Konta360.']);
        }

        $this->currentCompany->set($company);

        return $next($request);
    }
}
