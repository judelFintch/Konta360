<?php

namespace App\Modules\Companies\Services;

use App\Modules\Companies\Enums\SequenceType;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Hands out gap-free document numbers, one sequence per company, document
 * type and year. Must run inside the transaction that stores the number:
 * if that transaction rolls back, the number is given back with it.
 */
class DocumentNumberer
{
    public function __construct(private readonly CurrentCompany $currentCompany) {}

    public function next(SequenceType $type, DateTimeInterface $date): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Un numéro de pièce doit être attribué dans une transaction.');
        }

        $company = $this->currentCompany->get();
        $year = (int) $date->format('Y');
        $key = ['company_id' => $company->id, 'type' => $type->value, 'year' => $year];

        DB::table('document_sequences')->insertOrIgnore($key + ['last_number' => 0]);
        $last = DB::table('document_sequences')->where($key)->lockForUpdate()->value('last_number');
        $number = $last + 1;
        DB::table('document_sequences')->where($key)->update(['last_number' => $number]);

        return sprintf(
            '%s-%d-%s',
            $type->prefix($company),
            $year,
            str_pad((string) $number, $type->padding($company), '0', STR_PAD_LEFT)
        );
    }
}
