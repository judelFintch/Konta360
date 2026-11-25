<?php

namespace App\Http\Controllers;

use App\Models\BankReconciliation;
use App\Models\Company;
use App\Models\TreasuryAccount;
use App\Modules\Administration\Enums\Permission;
use App\Modules\Companies\Validation\CompanyRule;
use App\Modules\Treasury\Enums\TreasuryAccountType;
use App\Modules\Treasury\Services\TreasuryReportBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Printable treasury reports (A4 PDF, opened in the browser to print or
 * save): cash and bank journals, cash position, receipts and payments
 * statement, cash count report and bank reconciliation statement.
 */
class TreasuryReportController extends Controller
{
    public function __construct(private readonly TreasuryReportBuilder $reports) {}

    public function index(): View
    {
        $this->authorizeReports();

        return view('treasury.reports.index', [
            'accounts' => TreasuryAccount::query()->orderByDesc('is_active')->orderBy('currency')->orderBy('name')->get(),
            'dateFrom' => today()->startOfMonth()->toDateString(),
            'dateTo' => today()->toDateString(),
            'defaultCurrency' => Company::current()->default_currency ?: 'CDF',
        ]);
    }

    public function journal(Request $request): Response
    {
        $this->authorizeReports();
        $filters = $this->filters($request);
        $account = $filters['account'];

        $statements = ($account ? collect([$account]) : TreasuryAccount::query()->orderBy('currency')->orderBy('type')->orderBy('name')->get())
            ->map(fn (TreasuryAccount $item) => $this->reports->statement($item, $filters['dateFrom'], $filters['dateTo']))
            ->filter(fn (array $statement) => $account || $statement['account']->is_active || $statement['lines']->isNotEmpty() || abs($statement['opening']) > 0.001)
            ->values();

        $title = match ($account?->type) {
            TreasuryAccountType::Cash => 'Journal de caisse',
            TreasuryAccountType::Bank => 'Journal de banque',
            default => 'Journal de trésorerie',
        };

        return $this->pdf('treasury.reports.pdf.journal', [
            ...$filters,
            'title' => $title,
            'statements' => $statements,
        ], 'journal-tresorerie-'.$filters['dateFrom'].'-'.$filters['dateTo'].'.pdf', 'landscape');
    }

    public function entriesExits(Request $request): Response
    {
        $this->authorizeReports();
        $filters = $this->filters($request);
        $account = $filters['account'];
        if (! $account) {
            throw ValidationException::withMessages(['account_id' => 'Choisissez un compte pour établir le rapport.']);
        }
        $statement = $this->reports->statement($account, $filters['dateFrom'], $filters['dateTo']);

        return $this->pdf('treasury.reports.pdf.entries-exits', [
            ...$filters,
            'title' => 'Registre des entrées et sorties',
            'statement' => $statement,
        ], 'registre-entrees-sorties-'.$account->id.'-'.$filters['dateFrom'].'-'.$filters['dateTo'].'.pdf');
    }

    public function position(Request $request): Response
    {
        $this->authorizeReports();
        $filters = $this->filters($request);

        return $this->pdf('treasury.reports.pdf.position', [
            ...$filters,
            'title' => 'Situation de trésorerie',
            'groups' => $this->reports->position($filters['dateFrom'], $filters['dateTo']),
        ], 'situation-tresorerie-'.$filters['dateTo'].'.pdf', 'landscape');
    }

    public function flows(Request $request): Response
    {
        $this->authorizeReports();
        $filters = $this->filters($request);
        $currency = $filters['currency'] ?? (Company::current()->default_currency ?: 'CDF');

        return $this->pdf('treasury.reports.pdf.flows', [
            ...$filters,
            'title' => 'Tableau des encaissements et décaissements',
            'currency' => $currency,
            'flows' => $this->reports->flows($filters['dateFrom'], $filters['dateTo'], $currency),
        ], "flux-tresorerie-{$currency}-{$filters['dateFrom']}-{$filters['dateTo']}.pdf");
    }

    public function cashCount(Request $request): Response
    {
        $this->authorizeReports();
        $data = $request->validate([
            'account_id' => ['required', 'integer', CompanyRule::exists('treasury_accounts')],
            'date' => ['nullable', 'date', 'before_or_equal:today'],
        ]);
        $account = TreasuryAccount::query()->findOrFail($data['account_id']);
        if ($account->type !== TreasuryAccountType::Cash) {
            throw ValidationException::withMessages(['account_id' => 'Le procès-verbal d’arrêté de caisse concerne uniquement une caisse.']);
        }
        $date = $data['date'] ?? today()->toDateString();

        return $this->pdf('treasury.reports.pdf.cash-count', [
            'title' => 'Procès-verbal d’arrêté de caisse',
            ...$this->reports->cashCount($account, $date),
        ], 'pv-arrete-caisse-'.$date.'.pdf');
    }

    public function reconciliation(BankReconciliation $reconciliation): Response
    {
        $this->authorizeReports();

        return $this->pdf('treasury.reports.pdf.reconciliation', [
            'title' => 'État de rapprochement bancaire',
            ...$this->reports->reconciliation($reconciliation),
        ], 'rapprochement-'.($reconciliation->number ?: $reconciliation->id).'.pdf');
    }

    /**
     * @return array{dateFrom: string, dateTo: string, account: ?TreasuryAccount, currency: ?string}
     */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'account_id' => ['nullable', 'integer', CompanyRule::exists('treasury_accounts')],
            'currency' => ['nullable', 'in:CDF,USD'],
        ]);

        return [
            'dateFrom' => Carbon::parse($data['date_from'] ?? today()->startOfMonth())->toDateString(),
            'dateTo' => Carbon::parse($data['date_to'] ?? today())->toDateString(),
            'account' => isset($data['account_id']) ? TreasuryAccount::query()->find($data['account_id']) : null,
            'currency' => $data['currency'] ?? null,
        ];
    }

    /**
     * A4 PDF shown in the browser, with « Page x / y » on every page.
     */
    private function pdf(string $view, array $data, string $filename, string $orientation = 'portrait'): Response
    {
        $pdf = Pdf::loadView($view, $data + [
            'company' => Company::current(),
            'printedBy' => auth()->user(),
            'printedAt' => now(),
            'money' => fn ($value) => number_format((float) $value, 2, ',', ' '),
            'day' => fn ($value) => Carbon::parse($value)->format('d/m/Y'),
        ])->setPaper('a4', $orientation);
        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text($canvas->get_width() - 78, $canvas->get_height() - 28, 'Page {PAGE_NUM} / {PAGE_COUNT}', $font, 7, [0.42, 0.45, 0.5]);

        return $pdf->stream($filename);
    }

    private function authorizeReports(): void
    {
        abort_unless(
            auth()->user()->can(Permission::TreasuryManage->value)
            && auth()->user()->can(Permission::ReportsExport->value),
            403
        );
    }
}
