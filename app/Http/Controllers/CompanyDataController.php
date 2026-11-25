<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\TreasuryAccount;
use App\Modules\Companies\Services\CompanyDataExporter;
use App\Modules\Companies\Services\RealStartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The company's data and legal commitments: full export, acceptance of the
 * current terms of service and closure request (ADR 0003 §§ 4 to 6).
 */
class CompanyDataController extends Controller
{
    public function show(RealStartService $realStart): View
    {
        $company = Company::current()->load(['termsAcceptor', 'closureRequester']);

        return view('administration.data.show', [
            'company' => $company,
            'realStartPreview' => $realStart->preview($company),
            'realStartArchivePath' => $company->real_started_at ? $realStart->archivePath($company) : null,
            'treasuryAccounts' => TreasuryAccount::query()->orderBy('currency')->orderBy('name')->get(),
        ]);
    }

    public function export(CompanyDataExporter $exporter): BinaryFileResponse
    {
        $company = Company::current();
        $filename = 'konta360-'.Str::slug($company->name).'-'.now()->format('Y-m-d').'.zip';

        return response()->download($exporter->export($company), $filename, ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }

    public function startReal(Request $request, RealStartService $realStart): RedirectResponse
    {
        $company = Company::current();
        abort_if($company->real_started_at, 409, 'Le démarrage en réel a déjà été effectué.');
        abort_if($company->isClosed() || $company->isClosureRequested(), 409, 'Une société clôturée ou en cours de clôture ne peut pas démarrer en réel.');

        $accountIds = TreasuryAccount::query()->orderBy('id')->pluck('id')->map(fn ($id) => (string) $id)->all();
        $accountsRule = $accountIds ? 'required|array:'.implode(',', $accountIds) : 'nullable|array';
        $rules = [
            'company_name' => ['required', Rule::in([$company->name])],
            'password' => ['required', 'current_password'],
            'start_date' => ['required', 'date', 'before_or_equal:today'],
            'archive_confirmed' => ['accepted'],
            'accounts' => [$accountsRule],
        ];
        foreach ($accountIds as $accountId) {
            $rules["accounts.{$accountId}"] = ['array:name,type,currency,opening_balance,keep'];
            $rules["accounts.{$accountId}.keep"] = ['nullable', 'boolean'];
            $rules["accounts.{$accountId}.name"] = ['nullable', 'string', 'max:255'];
            $rules["accounts.{$accountId}.type"] = ['nullable', Rule::in(['cash', 'bank'])];
            $rules["accounts.{$accountId}.currency"] = ['nullable', Rule::in(['CDF', 'USD'])];
            $rules["accounts.{$accountId}.opening_balance"] = ['nullable', 'numeric', 'min:0', 'max:999999999999.99'];
        }

        $data = $request->validate($rules, [
            'company_name.in' => 'Saisissez exactement le nom de la société.',
            'archive_confirmed.accepted' => 'Confirmez que vous comprenez les données qui seront archivées.',
        ]);

        $accounts = [];
        foreach ($accountIds as $accountId) {
            $account = $data['accounts'][$accountId] ?? [];
            if (! filter_var($account['keep'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $accounts[$accountId] = ['keep' => false];
                continue;
            }
            foreach (['name', 'type', 'currency', 'opening_balance'] as $field) {
                if (! isset($account[$field]) || $account[$field] === '') {
                    throw ValidationException::withMessages(["accounts.{$accountId}.{$field}" => 'Complétez les informations du compte conservé.']);
                }
            }
            $accounts[$accountId] = [...$account, 'keep' => true];
        }
        $retainedAccounts = array_filter($accounts, fn (array $account) => $account['keep']);
        if ($accountIds && ! $retainedAccounts) {
            throw ValidationException::withMessages(['accounts' => 'Conservez au moins un compte de trésorerie pour démarrer avec un solde réel.']);
        }

        $uniqueNames = [];
        foreach ($retainedAccounts as $account) {
            $key = mb_strtolower(trim($account['name'])).'|'.$account['currency'];
            if (isset($uniqueNames[$key])) {
                throw ValidationException::withMessages(['accounts' => 'Chaque compte conservé doit avoir un nom unique dans sa devise.']);
            }
            $uniqueNames[$key] = true;
        }

        $realStart->start($company, $data['start_date'], $accounts, $request->user()->id);

        return back()->with('success', 'La société a démarré en réel. Les données précédentes sont archivées et les nouveaux soldes de départ sont en place.');
    }

    public function downloadRealStartArchive(RealStartService $realStart): BinaryFileResponse
    {
        $company = Company::current();
        $path = $realStart->archivePath($company);
        abort_unless($path, 404);

        return response()->download(
            Company::brandDisk()->path($path),
            'konta360-archive-essais-'.Str::slug($company->name).'.zip',
            ['Content-Type' => 'application/zip'],
        );
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
