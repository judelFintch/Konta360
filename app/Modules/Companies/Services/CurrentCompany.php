<?php

namespace App\Modules\Companies\Services;

use App\Models\Company;
use App\Modules\Companies\Exceptions\MissingCompanyContext;

/**
 * Holds the company the application is currently working for. It is set by
 * the SetCurrentCompany middleware from the authenticated user, or
 * explicitly (public verification page, provisioning, console tasks).
 *
 * Reading it while it is unset throws: business data must never be read
 * or written without knowing whose data it is.
 */
class CurrentCompany
{
    private ?Company $company = null;

    public function set(Company $company): void
    {
        $this->company = $company;
    }

    public function forget(): void
    {
        $this->company = null;
    }

    public function has(): bool
    {
        return $this->company !== null;
    }

    public function get(): Company
    {
        return $this->company ?? throw new MissingCompanyContext;
    }

    public function id(): int
    {
        return $this->get()->getKey();
    }

    /**
     * Runs the callback as the given company, then restores the previous
     * context whatever happens.
     *
     * @template T
     *
     * @param  callable(Company): T  $callback
     * @return T
     */
    public function runAs(Company $company, callable $callback): mixed
    {
        $previous = $this->company;
        $this->company = $company;

        try {
            return $callback($company);
        } finally {
            $this->company = $previous;
        }
    }
}
