<?php

use App\Models\AuthenticationCode;
use App\Models\Company;
use App\Models\Plan;
use App\Models\PlatformEvent;
use App\Models\User;
use App\Modules\Companies\Concerns\BelongsToCompany;
use App\Modules\Companies\Services\CompanyDataExporter;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Finder\Finder;

/*
 * Guards for ADR 0002 § Conséquences: forgetting one of these rules
 * silently exposes a company's data to the others.
 */

arch('every business model is confined to a company')
    ->expect('App\Models')
    ->toUseTrait(BelongsToCompany::class)
    // Not company data: the companies themselves, their users (see ADR 0002
    // § 4), the subscription plans, the sign-in codes, which are used before
    // any company is known (ADR 0004), and the platform's own log (ADR 0005).
    ->ignoring([Company::class, User::class, Plan::class, AuthenticationCode::class, PlatformEvent::class]);

it('uses no unscoped exists/unique validation rule on business tables', function () {
    $offenders = [];
    $pattern = "/'(exists|unique):(?!users\\b)|(?<!Company)Rule::(exists|unique)\\(/";

    foreach ((new Finder)->files()->in([app_path(), resource_path('views')])->name('*.php') as $file) {
        if (str_ends_with($file->getRealPath(), 'Validation/CompanyRule.php')) {
            continue;
        }
        foreach (explode("\n", $file->getContents()) as $number => $line) {
            // Tables shared by every company: users (email addresses are unique
            // across the platform) and plans.
            $sharedTable = str_contains($line, "Rule::unique('users')") || str_contains($line, 'User::class')
                || str_contains($line, "Rule::exists('plans'");
            if (preg_match($pattern, $line) && ! $sharedTable) {
                $offenders[] = $file->getRelativePathname().':'.($number + 1);
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('exports and purges every table that holds company data', function () {
    $companyTables = collect(Schema::getTableListing(schemaQualified: false))
        ->filter(fn (string $table) => Schema::hasColumn($table, 'company_id'))
        // users: exported and purged separately; platform_events: the
        // platform's log, never shown to the company, deleted with it.
        ->reject(fn (string $table) => in_array($table, ['users', 'platform_events'], true))
        ->sort()->values()->all();

    expect(collect(CompanyDataExporter::TABLES)->sort()->values()->all())->toBe($companyTables);
});
