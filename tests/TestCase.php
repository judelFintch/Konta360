<?php

namespace Tests;

use App\Models\Company;
use App\Modules\Companies\Services\CurrentCompany;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Business data set up outside of a request belongs to the company
        // created by the migrations, which default factory users join too.
        if (Schema::hasTable('companies') && $company = Company::query()->oldest('id')->first()) {
            app(CurrentCompany::class)->set($company);
        }
    }

    /**
     * Every request starts without a current company, as in production
     * where each request boots a fresh application; the test's own context
     * is restored afterwards.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $currentCompany = app(CurrentCompany::class);
        $previous = $currentCompany->has() ? $currentCompany->get() : null;
        $currentCompany->forget();

        try {
            return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
        } finally {
            $previous ? $currentCompany->set($previous) : $currentCompany->forget();
        }
    }

    /**
     * Runs the callback as another company, then comes back.
     */
    protected function asCompany(Company $company, callable $callback): mixed
    {
        return app(CurrentCompany::class)->runAs($company, $callback);
    }
}
