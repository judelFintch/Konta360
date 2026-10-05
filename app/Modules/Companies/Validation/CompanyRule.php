<?php

namespace App\Modules\Companies\Validation;

use App\Modules\Companies\Services\CurrentCompany;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Validation rules confined to the current company. The `exists:` and
 * `unique:` string rules query the database directly and bypass Eloquent
 * global scopes, so they must never be used on business tables.
 */
class CompanyRule
{
    public static function exists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->where('company_id', app(CurrentCompany::class)->id());
    }

    public static function unique(string $table, string $column = 'NULL'): Unique
    {
        return Rule::unique($table, $column)->where('company_id', app(CurrentCompany::class)->id());
    }
}
