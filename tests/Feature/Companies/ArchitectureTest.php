<?php

use App\Models\Company;
use App\Models\Plan;
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
    // Shared by every company: the companies themselves, their users (see
    // ADR 0002 § 4) and the subscription plans.
    ->ignoring([Company::class, User::class, Plan::class]);

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
        ->reject(fn (string $table) => $table === 'users')
        ->sort()->values()->all();

    expect(collect(CompanyDataExporter::TABLES)->sort()->values()->all())->toBe($companyTables);
});
