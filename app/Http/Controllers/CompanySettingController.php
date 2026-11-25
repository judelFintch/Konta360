<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Modules\Companies\Services\BrandImageProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CompanySettingController extends Controller
{
    public function edit(): View
    {
        return view('administration.company-settings.edit', ['company' => Company::current()]);
    }

    public function update(Request $request, BrandImageProcessor $images): RedirectResponse
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
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'remove_logo' => ['sometimes', 'boolean'],
            'signature' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'stamp' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'invoice_footer' => ['nullable', 'string', 'max:2000'],
            'document_primary_color' => ['required_unless:use_default_document_colors,1', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'use_default_document_colors' => ['sometimes', 'boolean'],
        ]);

        $company = Company::current();
        if ($request->boolean('use_default_document_colors')) {
            $data['document_primary_color'] = null;
        }
        unset($data['use_default_document_colors']);
        if ($request->boolean('remove_logo') && ! $request->hasFile('logo')) {
            if ($company->logo_path) {
                Company::brandDisk()->delete($company->logo_path);
            }
            $data['logo_path'] = null;
        }

        // Cleaned first (orientation, empty margins, size, PNG): a refused
        // image leaves the current one untouched.
        $processed = [];
        foreach (Company::BRAND_ASSETS as $asset) {
            if (! $request->hasFile($asset)) {
                continue;
            }
            try {
                $processed[$asset] = $images->process($request->file($asset)->getRealPath(), $asset);
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages([$asset => $exception->getMessage()]);
            }
        }

        $warnings = [];
        foreach ($processed as $asset => $result) {
            $pathColumn = $asset.'_path';
            if ($company->{$pathColumn}) {
                Company::brandDisk()->delete($company->{$pathColumn});
            }
            $data[$pathColumn] = $company->storageDirectory().'/'.$asset.'-'.Str::random(32).'.png';
            Company::brandDisk()->put($data[$pathColumn], $result['png']);
            $warnings = [...$warnings, ...$result['warnings']];
        }
        unset($data['logo'], $data['remove_logo'], $data['signature'], $data['stamp']);

        $company->update($data);

        return back()
            ->with('success', 'Les paramètres de l’entreprise ont été enregistrés.')
            ->with('warnings', $warnings);
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
        $path = $company->brandAssetPath($type);
        abort_unless($path !== null, 404);

        return response()->file($path, ['Cache-Control' => 'private, max-age=300']);
    }
}
