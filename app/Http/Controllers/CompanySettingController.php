<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CompanySettingController extends Controller
{
    public function edit(): View
    {
        return view('administration.company-settings.edit', ['company' => Company::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_form' => ['nullable', 'string', 'max:100'],
            'tax_identifier' => ['nullable', 'string', 'max:255'],
            'national_identifier' => ['nullable', 'string', 'max:255'],
            'cnss_number' => ['nullable', 'string', 'max:255'],
            'trade_register' => ['nullable', 'string', 'max:255'],
            'representative_name' => ['nullable', 'string', 'max:255'],
            'representative_title' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
            'default_currency' => ['required', 'in:CDF,USD'],
            'default_tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'default_payment_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'default_quote_validity_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:255'],
            'bank_swift' => ['nullable', 'string', 'max:50'],
            'mobile_money' => ['nullable', 'string', 'max:255'],
            'quote_prefix' => ['required', 'regex:/^[A-Z0-9-]+$/', 'max:20'],
            'invoice_prefix' => ['required', 'regex:/^[A-Z0-9-]+$/', 'max:20'],
            'credit_note_prefix' => ['required', 'regex:/^[A-Z0-9-]+$/', 'max:20'],
            'number_padding' => ['required', 'integer', 'min:3', 'max:10'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'signature' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'stamp' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'invoice_footer' => ['nullable', 'string', 'max:2000'],
        ]);

        $company = Company::current();
        foreach (['logo', 'signature', 'stamp'] as $asset) {
            if (! $request->hasFile($asset)) {
                continue;
            }
            $pathColumn = $asset.'_path';
            if ($company->{$pathColumn}) {
                Storage::disk('public')->delete($company->{$pathColumn});
            }
            $data[$pathColumn] = $request->file($asset)->store($company->storageDirectory(), 'public');
        }
        unset($data['logo'], $data['signature'], $data['stamp']);

        $company->update($data);

        return back()->with('success', 'Les paramètres de l’entreprise ont été enregistrés.');
    }

    /**
     * Branding files of the current company only.
     */
    public function asset(string $type): BinaryFileResponse
    {
        return static::assetResponse(Company::current(), $type);
    }

    public static function assetResponse(Company $company, string $type): BinaryFileResponse
    {
        abort_unless(in_array($type, ['logo', 'signature', 'stamp'], true), 404);
        $path = $company->{$type.'_path'};
        abort_unless($path && Storage::disk('public')->exists($path), 404);

        return response()->file(Storage::disk('public')->path($path));
    }
}
