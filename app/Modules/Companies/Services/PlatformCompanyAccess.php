<?php

namespace App\Modules\Companies\Services;

use App\Models\Company;
use App\Models\User;
use App\Modules\Administration\Enums\Permission;
use BackedEnum;
use Illuminate\Http\Request;

class PlatformCompanyAccess
{
    public const SESSION_KEY = 'platform_company_access_id';

    public function selectedCompany(Request $request): ?Company
    {
        if (! $request->user()?->is_platform_admin || ! $request->hasSession()) {
            return null;
        }

        $companyId = $this->selectedCompanyId($request);
        $company = $companyId ? Company::query()->find($companyId) : null;
        if (! $company) {
            $request->session()->forget(self::SESSION_KEY);
        }

        return $company;
    }

    public function begin(Request $request, Company $company): void
    {
        $request->session()->put(self::SESSION_KEY, $company->getKey());
    }

    public function end(Request $request): ?Company
    {
        $company = $this->selectedCompany($request);
        $request->session()->forget(self::SESSION_KEY);

        return $company;
    }

    public function isActiveFor(?User $user): bool
    {
        $request = request();

        if (! $user?->is_platform_admin || ! $request->hasSession() || $request->routeIs('platform.*')) {
            return false;
        }

        $companyId = $this->selectedCompanyId($request);
        $currentCompany = app(CurrentCompany::class);

        return $companyId !== null
            && $currentCompany->has()
            && $currentCompany->id() === $companyId;
    }

    public function grantsPermission(?User $user, mixed $permission): bool
    {
        if (! $this->isActiveFor($user)) {
            return false;
        }

        $permission = $permission instanceof BackedEnum ? $permission->value : $permission;

        return is_string($permission) && in_array($permission, Permission::values(), true);
    }

    private function selectedCompanyId(Request $request): ?int
    {
        if (! $request->hasSession()) {
            return null;
        }

        $companyId = $request->session()->get(self::SESSION_KEY);
        if (! is_int($companyId) && (! is_string($companyId) || ! ctype_digit($companyId))) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        return (int) $companyId;
    }
}
