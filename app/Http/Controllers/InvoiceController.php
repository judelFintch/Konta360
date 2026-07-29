<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceDraftRequest;
use App\Models\Invoice;
use App\Models\Quote;
use App\Modules\Administration\Enums\Permission;
use App\Modules\Invoices\Enums\InvoiceStatus;
use App\Modules\Quotes\Enums\QuoteStatus;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $this->requirePermission(Permission::InvoicesView);
        $search = trim((string) $request->query('search'));
        $status = $request->query('status');

        $invoices = Invoice::query()
            ->with('party')
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('number', 'like', "%{$search}%")
                    ->orWhereHas('party', fn ($query) => $query->where('name', 'like', "%{$search}%"));
            }))
            ->when(
                in_array($status, array_column(InvoiceStatus::cases(), 'value'), true),
                fn ($query) => $query->where('status', $status)
            )
            ->latest('issue_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('invoices.index', compact('invoices', 'search', 'status'));
    }

    public function show(Invoice $invoice): View
    {
        $this->requirePermission(Permission::InvoicesView);
        $invoice->load(['party', 'quote', 'lines', 'creator', 'payments.recorder']);

        return view('invoices.show', compact('invoice'));
    }

    public function print(Invoice $invoice): View
    {
        $this->requirePermission(Permission::InvoicesView);

        return view('documents.commercial', $this->documentData($invoice, false));
    }

    public function pdf(Invoice $invoice): Response
    {
        $this->requirePermission(Permission::InvoicesView);
        $filename = ($invoice->number ?: 'facture-brouillon-'.$invoice->id).'.pdf';

        return Pdf::loadView('documents.commercial', $this->documentData($invoice, true))
            ->setPaper('a4')
            ->download($filename);
    }

    public function convert(Quote $quote): RedirectResponse
    {
        $this->requirePermission(Permission::QuotesConvert);
        $this->requirePermission(Permission::InvoicesCreate);

        $invoice = DB::transaction(function () use ($quote) {
            $quote = Quote::query()->with('lines')->lockForUpdate()->findOrFail($quote->id);
            abort_unless(
                in_array($quote->status, [QuoteStatus::Draft, QuoteStatus::Sent, QuoteStatus::Accepted], true),
                409,
                'Ce devis ne peut pas être converti.'
            );
            abort_if(Invoice::where('quote_id', $quote->id)->exists(), 409, 'Ce devis a déjà été facturé.');

            $invoice = Invoice::create([
                'quote_id' => $quote->id,
                'party_id' => $quote->party_id,
                'status' => InvoiceStatus::Draft,
                'issue_date' => today(),
                'due_date' => today()->addDays(30),
                'currency' => $quote->currency,
                'notes' => $quote->notes,
                'subtotal' => $quote->subtotal,
                'discount_total' => $quote->discount_total,
                'tax_total' => $quote->tax_total,
                'total' => $quote->total,
                'created_by' => auth()->id(),
            ]);
            $invoice->lines()->createMany($quote->lines->map(fn ($line) => [
                'catalog_item_id' => $line->catalog_item_id,
                'position' => $line->position,
                'sku' => $line->sku,
                'description' => $line->description,
                'unit' => $line->unit,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'discount_rate' => $line->discount_rate,
                'tax_rate' => $line->tax_rate,
                'subtotal' => $line->subtotal,
                'discount_amount' => $line->discount_amount,
                'tax_amount' => $line->tax_amount,
                'total' => $line->total,
            ])->all());
            $quote->update(['status' => QuoteStatus::Accepted]);

            return $invoice;
        });

        return to_route('invoices.show', $invoice)->with('success', 'La facture brouillon a été créée.');
    }

    public function edit(Invoice $invoice): View
    {
        $this->requirePermission(Permission::InvoicesUpdateDraft);
        $this->ensureDraft($invoice);

        return view('invoices.edit', compact('invoice'));
    }

    public function update(InvoiceDraftRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->requirePermission(Permission::InvoicesUpdateDraft);
        $this->ensureDraft($invoice);
        $invoice->update($request->validated());

        return to_route('invoices.show', $invoice)->with('success', 'La facture brouillon a été mise à jour.');
    }

    public function validateInvoice(Invoice $invoice): RedirectResponse
    {
        $this->requirePermission(Permission::InvoicesValidate);
        $this->ensureDraft($invoice);

        $invoice->update([
            'number' => sprintf('FAC-%s-%05d', $invoice->issue_date->format('Y'), $invoice->id),
            'status' => InvoiceStatus::Validated,
            'validated_at' => now(),
            'validated_by' => auth()->id(),
        ]);

        return back()->with('success', 'La facture a été validée et numérotée.');
    }

    public function cancel(Invoice $invoice): RedirectResponse
    {
        $this->requirePermission(Permission::InvoicesCancel);
        abort_if($invoice->status === InvoiceStatus::Cancelled, 409, 'Cette facture est déjà annulée.');
        $invoice->update([
            'status' => InvoiceStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by' => auth()->id(),
        ]);

        return back()->with('success', 'La facture a été annulée.');
    }

    private function ensureDraft(Invoice $invoice): void
    {
        abort_unless($invoice->status === InvoiceStatus::Draft, 409, 'Seule une facture brouillon peut être modifiée.');
    }

    private function documentData(Invoice $invoice, bool $forPdf): array
    {
        $invoice->load(['party', 'lines']);

        return [
            'document' => $invoice,
            'documentType' => 'Facture',
            'documentNumber' => $invoice->number ?: 'Brouillon #'.$invoice->id,
            'secondaryDateLabel' => 'Échéance',
            'secondaryDate' => $invoice->due_date,
            'backUrl' => route('invoices.show', $invoice),
            'pdfUrl' => route('invoices.pdf', $invoice),
            'forPdf' => $forPdf,
        ];
    }

    private function requirePermission(Permission $permission): void
    {
        abort_unless(auth()->user()->can($permission->value), 403);
    }
}
