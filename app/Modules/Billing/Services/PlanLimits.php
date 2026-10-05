<?php

namespace App\Modules\Billing\Services;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Limits of the company's plan. A null limit, an exempt company or a company
 * without a plan is unlimited.
 */
class PlanLimits
{
    public function activeUsers(Company $company): int
    {
        return User::query()->whereBelongsTo($company)->where('is_active', true)->count();
    }

    public function invoicesValidatedThisMonth(): int
    {
        return Invoice::query()->whereBetween('validated_at', [now()->startOfMonth(), now()->endOfMonth()])->count();
    }

    public function ensureCanAddUser(Company $company): void
    {
        $max = $this->limit($company, 'max_users');

        if ($max !== null && $this->activeUsers($company) >= $max) {
            throw ValidationException::withMessages([
                'email' => "Votre formule {$company->plan->name} est limitée à {$max} utilisateur(s) actif(s). Désactivez un compte ou changez de formule.",
            ]);
        }
    }

    public function ensureCanValidateInvoice(Company $company): void
    {
        $max = $this->limit($company, 'max_invoices_per_month');

        if ($max !== null && $this->invoicesValidatedThisMonth() >= $max) {
            throw ValidationException::withMessages([
                'invoice' => "Votre formule {$company->plan->name} est limitée à {$max} facture(s) validée(s) par mois. Changez de formule pour continuer ce mois-ci.",
            ]);
        }
    }

    public function limit(Company $company, string $limit): ?int
    {
        if ($company->billing_exempt || ! $company->plan) {
            return null;
        }

        return $company->plan->{$limit};
    }
}
