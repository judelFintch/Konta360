<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Companies\Concerns\BelongsToCompany;
use Symfony\Component\Finder\Finder;

/*
 * Guards for ADR 0002 § Conséquences: forgetting one of these rules
 * silently exposes a company's data to the others.
 */

arch('every business model is confined to a company')
    ->expect('App\Models')
    ->toUseTrait(BelongsToCompany::class)
    ->ignoring([Company::class, User::class]);

it('uses no unscoped exists/unique validation rule on business tables', function () {
    $offenders = [];
    $pattern = "/'(exists|unique):(?!users\\b)|(?<!Company)Rule::(exists|unique)\\(/";

    foreach ((new Finder)->files()->in([app_path(), resource_path('views')])->name('*.php') as $file) {
        if (str_ends_with($file->getRealPath(), 'Validation/CompanyRule.php')) {
            continue;
        }
        foreach (explode("\n", $file->getContents()) as $number => $line) {
            // Email addresses are unique across the whole platform (users table).
            $usersOnly = str_contains($line, "Rule::unique('users')") || str_contains($line, 'User::class');
            if (preg_match($pattern, $line) && ! $usersOnly) {
                $offenders[] = $file->getRelativePathname().':'.($number + 1);
            }
        }
    }

    expect($offenders)->toBe([]);
});
