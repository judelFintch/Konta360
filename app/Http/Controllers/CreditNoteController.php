<?php

namespace App\Http\Controllers;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Modules\Accounting\Services\AccountingService;
use App\Modules\Administration\Enums\Permission;
use App\Modules\Companies\Enums\SequenceType;
use App\Modules\CreditNotes\Enums\CreditNoteStatus;
use App\Modules\Documents\Services\CommercialDocumentPresenter;
use App\Modules\Invoices\Enums\InvoiceStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CreditNoteController extends Controller
{
    public function index(Request $request): View
    {
        $this->requireViewPermission();
        $search = trim((string) $request->query('search'));
        $creditNotes = CreditNote::query()
            ->with(['party', 'invoice'])
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('number', 'like', "%{$search}%")
                    ->orWhereHas('invoice', fn ($query) => $query->where('number', 'like', "%{$search}%"))
                    ->orWhereHas('party', fn ($query) => $query->where('name', 'like', "%{$search}%"));
            }))
            ->latest('issue_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $summary = CreditNote::query()->get()->groupBy('currency')->map(fn ($notes) => [
            'total' => round((float) $notes->sum('total'), 2),
            'month' => round((float) $notes->filter(fn (CreditNote $note) => $note->issue_date->gte(today()->startOfMonth()))->sum('total'), 2),
            'count' => $notes->count(),
        ]);

        return view('credit-notes.index', compact('creditNotes', 'search', 'summary'));
    }

    public function create(Invoice $invoice): View
    {
        $this->requirePermission(Permission::CreditNotesCreate);
        abort_unless($invoice->status === InvoiceStatus::Validated, 409, 'Un avoir exige une facture validée.');
        $invoice->load(['party', 'lines.creditNoteLines']);

        return view('credit-notes.create', compact('invoice'));
    }

    public function store(Request $request, Invoice $invoice, AccountingService $accounting): RedirectResponse
    {
        $this->requirePermission(Permission::CreditNotesCreate);
        $data = $request->validate([
            'issue_date' => ['required', 'date', 'after_or_equal:'.$invoice->issue_date->format('Y-m-d')],
            'reason' => ['required', 'string', 'max:2000'],
            'lines' => ['required', 'array'],
            'lines.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $creditNote = DB::transaction(function () use ($invoice, $data, $accounting) {
            $invoice = Invoice::query()->with('lines')->lockForUpdate()->findOrFail($invoice->id);
            abort_unless($invoice->status === InvoiceStatus::Validated, 409, 'Un avoir exige une facture validée.');

            $position = 0;
            $lines = [];
            foreach ($invoice->lines as $invoiceLine) {
                $quantity = round((float) ($data['lines'][$invoiceLine->id] ?? 0), 3);
                if ($quantity <= 0) {
                    continue;
                }

                $available = $invoiceLine->creditableQuantity();
                if ($quantity > $available) {
                    throw ValidationException::withMessages([
                        "lines.{$invoiceLine->id}" => "La quantité dépasse le solde disponible de {$available}.",
                    ]);
                }

                $subtotal = round($quantity * (float) $invoiceLine->unit_price, 2);
                $discount = round($subtotal * (float) $invoiceLine->discount_rate / 100, 2);
                $tax = round(($subtotal - $discount) * (float) $invoiceLine->tax_rate / 100, 2);
                $lines[] = [
                    'invoice_line_id' => $invoiceLine->id,
                    'position' => ++$position,
                    'sku' => $invoiceLine->sku,
                    'description' => $invoiceLine->description,
                    'unit' => $invoiceLine->unit,
                    'quantity' => $quantity,
                    'unit_price' => $invoiceLine->unit_price,
                    'discount_rate' => $invoiceLine->discount_rate,
                    'tax_rate' => $invoiceLine->tax_rate,
                    'subtotal' => $subtotal,
                    'discount_amount' => $discount,
                    'tax_amount' => $tax,
                    'total' => round($subtotal - $discount + $tax, 2),
                ];
            }

            if ($lines === []) {
                throw ValidationException::withMessages(['lines' => 'Indiquez au moins une quantité à créditer.']);
            }

            $creditNote = CreditNote::create([
                'invoice_id' => $invoice->id,
                'party_id' => $invoice->party_id,
                'status' => CreditNoteStatus::Issued,
                'issue_date' => $data['issue_date'],
                'currency' => $invoice->currency,
                'reason' => $data['reason'],
                'subtotal' => round((float) collect($lines)->sum('subtotal'), 2),
                'discount_total' => round((float) collect($lines)->sum('discount_amount'), 2),
                'tax_total' => round((float) collect($lines)->sum('tax_amount'), 2),
                'total' => round((float) collect($lines)->sum('total'), 2),
                'created_by' => auth()->id(),
            ]);
            $creditNote->update(['number' => SequenceType::CreditNote->nextNumber($creditNote->issue_date)]);
            $creditNote->lines()->createMany($lines);
            $accounting->postCreditNote($creditNote, auth()->id());

            return $creditNote;
        });

        return to_route('credit-notes.show', $creditNote)->with('success', 'L’avoir a été émis et comptabilisé.');
    }

    public function show(CreditNote $creditNote): View
    {
        $this->requireViewPermission();
        $creditNote->load(['party', 'invoice', 'lines', 'creator']);
        $presenter = app(CommercialDocumentPresenter::class);

        return view('credit-notes.show', [
            'creditNote' => $creditNote,
            'fingerprint' => $presenter->fingerprint($creditNote),
            'verificationUrl' => $presenter->verificationUrl($creditNote),
        ]);
    }

    public function print(CreditNote $creditNote): View
    {
        $this->requireViewPermission();

        return view('documents.commercial', $this->documentData($creditNote, false));
    }

    public function pdf(CreditNote $creditNote): Response
    {
        $this->requireViewPermission();

        return app(CommercialDocumentPresenter::class)
            ->download($this->documentData($creditNote, true), $creditNote->number.'.pdf');
    }

    private function documentData(CreditNote $creditNote, bool $forPdf): array
    {
        $creditNote->load(['party', 'lines', 'invoice', 'creator']);

        return [
            ...app(CommercialDocumentPresenter::class)->present($creditNote),
            'document' => $creditNote,
            'documentType' => 'Avoir',
            'documentNumber' => $creditNote->number,
            'reference' => ['Facture d’origine', $creditNote->invoice->number.' du '.$creditNote->invoice->issue_date->format('d/m/Y')],
            'notesLabel' => 'Motif de l’avoir',
            'notesText' => $creditNote->reason,
            'backUrl' => route('credit-notes.show', $creditNote),
            'pdfUrl' => route('credit-notes.pdf', $creditNote),
            'forPdf' => $forPdf,
        ];
    }

    private function requireViewPermission(): void
    {
        abort_unless(auth()->user()->can(Permission::InvoicesView->value), 403);
    }

    private function requirePermission(Permission $permission): void
    {
        abort_unless(auth()->user()->can($permission->value), 403);
    }
}
