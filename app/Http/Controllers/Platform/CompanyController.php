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
            ->with(['users' => fn ($query) => $query->role(Role::Administrateur->value)->select(['id', 'company_id', 'name', 'email'])])
            ->when($search, fn ($query) => $query->where('name', 'like', "%{$search}%"))
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
        $company->forceFill(['suspended_at' => null])->save();

        return back()->with('success', "L’accès de « {$company->name} » est rétabli.");
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
