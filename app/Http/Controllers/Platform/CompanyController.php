<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Modules\Administration\Enums\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Konta360 operators: list the subscribing companies and suspend or
 * reactivate their access. Deliberately shows no accounting data
 * (ADR 0002 § 8).
 */
class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $companies = Company::query()
            ->withCount('users')
            ->with('plan')
            ->with(['users' => fn ($query) => $query->role(Role::Administrateur->value)->select(['id', 'company_id', 'name', 'email'])])
            ->when($search, fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->when($request->boolean('closure'), fn ($query) => $query->whereNotNull('closure_requested_at')->whereNull('closed_at'))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('platform.companies.index', compact('companies', 'search'));
    }

    public function suspend(Company $company): RedirectResponse
    {
        $company->forceFill(['suspended_at' => now()])->save();

        return back()->with('success', "L’accès de « {$company->name} » est suspendu.");
    }

    public function reactivate(Company $company): RedirectResponse
    {
        abort_if($company->isClosed(), 409, 'Une société clôturée ne peut pas être rétablie.');
        $company->forceFill(['suspended_at' => null])->save();

        return back()->with('success', "L’accès de « {$company->name} » est rétabli.");
    }

    /**
     * Complimentary access: no trial, no payment, no plan limits.
     */
    public function toggleExempt(Company $company): RedirectResponse
    {
        $company->forceFill(['billing_exempt' => ! $company->billing_exempt])->save();

        return back()->with('success', $company->billing_exempt
            ? "« {$company->name} » bénéficie d’un accès offert."
            : "« {$company->name} » est de nouveau soumise à l’abonnement.");
    }

    /**
     * Closes a company whose administrator asked for it: access ends, the
     * data is kept for the legal retention period, then may be purged with
     * `php artisan konta360:purge-company`.
     */
    public function close(Company $company): RedirectResponse
    {
        abort_unless($company->isClosureRequested(), 409, 'Cette société n’a pas demandé sa clôture.');
        $company->forceFill(['closed_at' => now(), 'suspended_at' => $company->suspended_at ?? now()])->save();

        return back()->with('success', "« {$company->name} » est clôturée. Ses données sont conservées pendant la durée légale.");
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
