<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Modules\Companies\Services\CompanyDataExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The company's data and legal commitments: full export, acceptance of the
 * current terms of service and closure request (ADR 0003 §§ 4 to 6).
 */
class CompanyDataController extends Controller
{
    public function show(): View
    {
        return view('administration.data.show', ['company' => Company::current()->load(['termsAcceptor', 'closureRequester'])]);
    }

    public function export(CompanyDataExporter $exporter): BinaryFileResponse
    {
        $company = Company::current();
        $filename = 'konta360-'.Str::slug($company->name).'-'.now()->format('Y-m-d').'.zip';

        return response()->download($exporter->export($company), $filename, ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }

    public function acceptTerms(Request $request): RedirectResponse
    {
        Company::current()->forceFill([
            'terms_version' => config('konta360.terms_version'),
            'terms_accepted_at' => now(),
            'terms_accepted_by' => $request->user()->id,
        ])->save();

        return back()->with('success', 'Les conditions générales et la politique de confidentialité ont été acceptées.');
    }

    /**
     * Closing is final for the company's users: it is confirmed with the
     * administrator's password and the company's exact name.
     */
    public function requestClosure(Request $request): RedirectResponse
    {
        $company = Company::current();
        $request->validate([
            'password' => ['required', 'current_password'],
            'company_name' => ['required', Rule::in([$company->name])],
        ], ['company_name.in' => 'Saisissez exactement la raison sociale de la société.']);

        $company->forceFill(['closure_requested_at' => now(), 'closure_requested_by' => $request->user()->id])->save();

        return back()->with('success', 'La demande de clôture a été transmise à l’équipe Konta360. Pensez à télécharger l’export de vos données.');
    }

    public function cancelClosure(): RedirectResponse
    {
        $company = Company::current();
        abort_if($company->isClosed(), 409);
        $company->forceFill(['closure_requested_at' => null, 'closure_requested_by' => null])->save();

        return back()->with('success', 'La demande de clôture a été annulée.');
    }
}
