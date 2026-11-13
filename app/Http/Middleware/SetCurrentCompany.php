<?php

namespace App\Http\Middleware;

use App\Modules\Companies\Services\CurrentCompany;
use App\Modules\Companies\Services\PlatformCompanyAccess;
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
    public function __construct(
        private readonly CurrentCompany $currentCompany,
        private readonly PlatformCompanyAccess $platformCompanyAccess,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->currentCompany->forget();

        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $company = $user->company;

        if (! $company) {
            if ($user->is_platform_admin) {
                if ($request->routeIs('platform.*', 'legal.*', 'pricing', 'verification.*', 'password.*')) {
                    return $next($request);
                }

                $company = $this->platformCompanyAccess->selectedCompany($request);
                if (! $company) {
                    return redirect()->route('platform.dashboard');
                }

                if ($company->isSuspended() || $company->isClosed()) {
                    $this->platformCompanyAccess->end($request);

                    return redirect()->route('platform.companies.show', $company)
                        ->withErrors(['support_access' => 'Une société suspendue ou clôturée ne peut pas être ouverte en mode assistance.']);
                }

                $this->currentCompany->set($company);

                return $next($request);
            }

            abort(403, 'Ce compte n’est rattaché à aucune société.');
        }

        if ($company->isSuspended()) {
            if ($user->is_platform_admin) {
                return redirect()->route('platform.companies.show', $company)
                    ->withErrors(['support_access' => 'Une société suspendue ne peut pas être ouverte en mode assistance.']);
            }

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
