<?php

namespace App\Http\Requests;

use App\Models\AccountingPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AccountingPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $overlap = AccountingPeriod::query()
                    ->where('starts_on', '<=', $this->input('ends_on'))
                    ->where('ends_on', '>=', $this->input('starts_on'))
                    ->exists();

                if ($overlap) {
                    $validator->errors()->add('starts_on', 'Cette période chevauche un exercice existant.');
                }
            },
        ];
    }
}
