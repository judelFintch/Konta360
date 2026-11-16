<?php

namespace App\Http\Requests;

use App\Modules\Companies\Services\PlatformCompanyAccess;

class InvoiceDeductionCorrectionRequest extends InvoiceDraftRequest
{
    public function authorize(): bool
    {
        return app(PlatformCompanyAccess::class)->isActiveFor($this->user());
    }

    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['issue_date'], $rules['due_date'], $rules['notes'], $rules['language']);
        $rules['deductions.*.received_on'] = array_map(
            fn ($rule) => $rule === 'before_or_equal:issue_date'
                ? 'before_or_equal:'.$this->route('invoice')->issue_date->format('Y-m-d') : $rule,
            $rules['deductions.*.received_on']
        );

        return [...$rules,
            'deductions.*.id' => ['nullable', 'integer', 'min:1', 'distinct'],
            'deduction_version' => ['required', 'string', 'size:64'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }
}
