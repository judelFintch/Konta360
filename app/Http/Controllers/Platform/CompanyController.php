<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Plan;
use App\Models\PlatformEvent;
use App\Modules\Administration\Enums\Role;
use App\Modules\Billing\Enums\SubscriptionPaymentMethod;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Services\PlanLimits;
use App\Modules\Companies\Services\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Konta360 operators follow and manage their subscribers (ADR 0005). They
 * see each company's identity, subscription, usage and users, never its
 * accounting data (ADR 0002 § 8).
 */
class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        return view('platform.companies.index', [
            'companies' => $this->query($filters)
                ->withCount(['users', 'users as active_users_count' => fn ($query) => $query->where('is_active', true)])
                ->with(['plan', 'users' => fn ($query) => $query->role(Role::Administrateur->value)->select(['id', 'company_id', 'name', 'email'])])
                ->paginate(30)
                ->withQueryString(),
            'filters' => $filters,
            'plans' => Plan::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function show(Company $company, PlanLimits $limits, CurrentCompany $currentCompany): View
    {
        $company->load(['plan', 'users.roles', 'termsAcceptor', 'closureRequester']);

        return view('platform.companies.show', [
            'company' => $company,
            'status' => $company->subscriptionStatus(),
            // Counts only, read as the company: no accounting data is shown.
            'usage' => [
                'users' => [$limits->activeUsers($company), $limits->limit($company, 'max_users')],
                'invoices' => [
                    $currentCompany->runAs($company, fn () => $limits->invoicesValidatedThisMonth()),
                    $limits->limit($company, 'max_invoices_per_month'),
                ],
            ],
            'payments' => $company->subscriptionPayments()->with(['plan', 'submitter', 'reviewer'])->latest('id')->get(),
            'events' => $company->platformEvents()->with('actor')->latest('id')->limit(30)->get(),
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'purchasablePlans' => Plan::query()->purchasable()->get(),
            'methods' => SubscriptionPaymentMethod::cases(),
        ]);
    }

    public function updateNotes(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate(['platform_notes' => ['nullable', 'string', 'max:5000']]);
        $company->forceFill(['platform_notes' => $data['platform_notes']])->save();

        return back()->with('success', 'Notes enregistrées.');
    }

    public function export(Request $request): StreamedResponse
    {
        $companies = $this->query($this->filters($request))
            ->withCount(['users as active_users_count' => fn ($query) => $query->where('is_active', true)])
            ->with(['plan', 'users' => fn ($query) => $query->role(Role::Administrateur->value)->select(['id', 'company_id', 'email'])])
            ->get();

        return response()->streamDownload(function () use ($companies) {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['id', 'societe', 'administrateurs', 'formule', 'statut', 'acces_jusqu_au', 'utilisateurs_actifs', 'derniere_connexion', 'inscrite_le', 'suspendue', 'cloturee'], ';');
            foreach ($companies as $company) {
                fputcsv($output, array_map(fn ($value) => preg_match('/^[=+\-@]/', (string) $value) ? "'".$value : $value, [
                    $company->id,
                    $company->name,
                    $company->users->pluck('email')->implode(', '),
                    $company->plan?->name,
                    $company->subscriptionStatus()->label(),
                    $company->accessEndsOn()?->format('Y-m-d'),
                    $company->active_users_count,
                    $company->users_max_last_login_at,
                    $company->created_at?->format('Y-m-d'),
                    $company->isSuspended() ? 'oui' : 'non',
                    $company->isClosed() ? 'oui' : 'non',
                ]), ';');
            }
            fclose($output);
        }, 'konta360-abonnes-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function suspend(Request $request, Company $company): RedirectResponse
    {
        $company->forceFill(['suspended_at' => now()])->save();
        PlatformEvent::record($request->user(), $company, 'suspension', 'Accès suspendu');

        return back()->with('success', "L’accès de « {$company->name} » est suspendu.");
    }

    public function reactivate(Request $request, Company $company): RedirectResponse
    {
        abort_if($company->isClosed(), 409, 'Une société clôturée ne peut pas être rétablie.');
        $company->forceFill(['suspended_at' => null])->save();
        PlatformEvent::record($request->user(), $company, 'reactivation', 'Accès rétabli');

        return back()->with('success', "L’accès de « {$company->name} » est rétabli.");
    }

    /**
     * Complimentary access: no evaluation, no payment, no plan limits.
     */
    public function toggleExempt(Request $request, Company $company): RedirectResponse
    {
        $company->forceFill(['billing_exempt' => ! $company->billing_exempt])->save();
        PlatformEvent::record($request->user(), $company, 'exemption', $company->billing_exempt ? 'Accès offert accordé' : 'Accès offert retiré');

        return back()->with('success', $company->billing_exempt
            ? "« {$company->name} » bénéficie d’un accès offert."
            : "« {$company->name} » est de nouveau soumise à l’abonnement.");
    }

    /**
     * Closes a company whose administrator asked for it: access ends, the
     * data is kept for the legal retention period, then may be purged with
     * `php artisan konta360:purge-company`.
     */
    public function close(Request $request, Company $company): RedirectResponse
    {
        abort_unless($company->isClosureRequested(), 409, 'Cette société n’a pas demandé sa clôture.');
        $company->forceFill(['closed_at' => now(), 'suspended_at' => $company->suspended_at ?? now()])->save();
        PlatformEvent::record($request->user(), $company, 'closure', 'Société clôturée à sa demande');

        return back()->with('success', "« {$company->name} » est clôturée. Ses données sont conservées pendant la durée légale.");
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * @return array{search: string, status: ?SubscriptionStatus, plan: ?int, access: ?string, sort: string}
     */
    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search')),
            'status' => SubscriptionStatus::tryFrom((string) $request->query('status')),
            'plan' => $request->integer('plan') ?: null,
            'access' => in_array($request->query('access'), ['suspended', 'closure', 'closed'], true) ? $request->query('access') : null,
            'sort' => in_array($request->query('sort'), ['name', 'ends', 'login'], true) ? $request->query('sort') : 'recent',
        ];
    }

    /**
     * Last sign-in of the company's users is always selected, for display
     * and for sorting.
     */
    private function query(array $filters): Builder
    {
        return Company::query()
            ->withMax('users', 'last_login_at')
            ->when($filters['search'], fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('tax_identifier', 'like', "%{$search}%")
                ->orWhereHas('users', fn (Builder $query) => $query->where('email', 'like', "%{$search}%"))))
            ->when($filters['status'], fn (Builder $query, SubscriptionStatus $status) => $query->whereNull('closed_at')->withSubscriptionStatus($status))
            ->when($filters['plan'], fn (Builder $query, int $plan) => $query->where('plan_id', $plan))
            ->when($filters['access'] === 'suspended', fn (Builder $query) => $query->whereNotNull('suspended_at')->whereNull('closed_at'))
            ->when($filters['access'] === 'closure', fn (Builder $query) => $query->whereNotNull('closure_requested_at')->whereNull('closed_at'))
            ->when($filters['access'] === 'closed', fn (Builder $query) => $query->whereNotNull('closed_at'))
            ->when($filters['sort'] === 'name', fn (Builder $query) => $query->orderBy('name'))
            ->when($filters['sort'] === 'ends', fn (Builder $query) => $query->orderByRaw('coalesce(subscription_ends_at, trial_ends_at) is null, coalesce(subscription_ends_at, trial_ends_at)'))
            ->when($filters['sort'] === 'login', fn (Builder $query) => $query->orderByRaw('users_max_last_login_at is null, users_max_last_login_at desc'))
            ->when($filters['sort'] === 'recent', fn (Builder $query) => $query->latest('id'));
    }
}
