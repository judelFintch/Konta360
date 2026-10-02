<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuoteRequest;
use App\Models\CatalogItem;
use App\Models\CompanySetting;
use App\Models\Party;
use App\Models\Quote;
use App\Modules\Administration\Enums\Permission;
use App\Modules\Documents\Services\CommercialDocumentPresenter;
use App\Modules\Parties\Enums\PartyType;
use App\Modules\Quotes\Enums\QuoteStatus;
use App\Modules\Quotes\Services\QuoteCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class QuoteController extends Controller
{
    public function index(Request $request): View
    {
        $this->requirePermission(Permission::QuotesView);
        $search = trim((string) $request->query('search'));
        $status = $request->query('status');

        $quotes = Quote::query()
            ->with('party')
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('number', 'like', "%{$search}%")
                    ->orWhereHas('party', fn ($query) => $query->where('name', 'like', "%{$search}%"));
            }))
            ->when(
                in_array($status, array_column(QuoteStatus::cases(), 'value'), true),
                fn ($query) => $query->where('status', $status)
            )
            ->latest('issue_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('quotes.index', compact('quotes', 'search', 'status'));
    }

    public function create(): View
    {
        $this->requirePermission(Permission::QuotesCreate);

        return view('quotes.create', $this->formData());
    }

    public function store(QuoteRequest $request, QuoteCalculator $calculator): RedirectResponse
    {
        $this->requirePermission(Permission::QuotesCreate);
        $data = $request->validated();
        $totals = $calculator->calculate($data['lines'], $data['currency']);

        $quote = DB::transaction(function () use ($data, $totals) {
            $quote = Quote::create([
                ...collect($data)->except('lines')->all(),
                ...collect($totals)->except('lines')->all(),
                'status' => QuoteStatus::Draft,
                'created_by' => auth()->id(),
            ]);
            $quote->update(['number' => CompanySetting::current()->documentNumber('quote', $quote->id, $quote->issue_date)]);
            $quote->lines()->createMany($totals['lines']);

            return $quote;
        });

        return to_route('quotes.show', $quote)->with('success', 'Le devis a été créé.');
    }

    public function show(Quote $quote): View
    {
        $this->requirePermission(Permission::QuotesView);
        $quote->load(['party', 'lines', 'creator', 'invoice']);

        return view('quotes.show', compact('quote'));
    }

    public function print(Quote $quote): View
    {
        $this->requirePermission(Permission::QuotesView);

        return view('documents.commercial', $this->documentData($quote, false));
    }

    public function pdf(Quote $quote): Response
    {
        $this->requirePermission(Permission::QuotesView);

        return app(CommercialDocumentPresenter::class)
            ->download($this->documentData($quote, true), $quote->number.'.pdf');
    }

    public function edit(Quote $quote): View
    {
        $this->requirePermission(Permission::QuotesCreate);
        abort_unless($quote->status === QuoteStatus::Draft, 409, 'Seul un devis brouillon peut être modifié.');
        $quote->load('lines');

        return view('quotes.edit', [...$this->formData(), 'quote' => $quote]);
    }

    public function update(QuoteRequest $request, Quote $quote, QuoteCalculator $calculator): RedirectResponse
    {
        $this->requirePermission(Permission::QuotesCreate);
        abort_unless($quote->status === QuoteStatus::Draft, 409, 'Seul un devis brouillon peut être modifié.');
        $data = $request->validated();
        $totals = $calculator->calculate($data['lines'], $data['currency']);

        DB::transaction(function () use ($quote, $data, $totals) {
            $quote->update([
                ...collect($data)->except('lines')->all(),
                ...collect($totals)->except('lines')->all(),
            ]);
            $quote->lines()->delete();
            $quote->lines()->createMany($totals['lines']);
        });

        return to_route('quotes.show', $quote)->with('success', 'Le devis a été mis à jour.');
    }

    public function markAsSent(Quote $quote): RedirectResponse
    {
        $this->requirePermission(Permission::QuotesCreate);
        abort_unless($quote->status === QuoteStatus::Draft, 409, 'Ce devis ne peut plus être envoyé.');
        $quote->update(['status' => QuoteStatus::Sent]);

        return back()->with('success', 'Le devis est maintenant marqué comme envoyé.');
    }

    private function formData(): array
    {
        return [
            'parties' => Party::query()
                ->where('is_active', true)
                ->whereIn('type', [PartyType::Customer, PartyType::Both])
                ->orderBy('name')
                ->get(),
            'catalogItems' => CatalogItem::query()->where('is_active', true)->orderBy('name')->get(),
        ];
    }

    private function documentData(Quote $quote, bool $forPdf): array
    {
        $quote->load(['party', 'lines', 'creator']);

        return [
            ...app(CommercialDocumentPresenter::class)->present($quote),
            'document' => $quote,
            'documentType' => 'Devis',
            'documentNumber' => $quote->number,
            'secondaryDateLabel' => 'Valable jusqu’au',
            'secondaryDate' => $quote->valid_until,
            'backUrl' => route('quotes.show', $quote),
            'pdfUrl' => route('quotes.pdf', $quote),
            'forPdf' => $forPdf,
        ];
    }

    private function requirePermission(Permission $permission): void
    {
        abort_unless(auth()->user()->can($permission->value), 403);
    }
}
