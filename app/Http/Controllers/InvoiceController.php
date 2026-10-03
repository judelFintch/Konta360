<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceDraftRequest;
use App\Models\CompanySetting;
use App\Models\Invoice;
use App\Models\InvoiceDeduction;
use App\Models\Quote;
use App\Models\TreasuryAccount;
use App\Models\User;
use App\Modules\Accounting\Services\AccountingService;
use App\Modules\Administration\Enums\Permission;
use App\Modules\Documents\Enums\DocumentLanguage;
use App\Modules\Documents\Services\CommercialDocumentPresenter;
use App\Modules\Invoices\Enums\DeductionType;
use App\Modules\Invoices\Enums\InvoiceStatus;
use App\Modules\Payments\Services\PaymentRecorder;
use App\Modules\Quotes\Enums\QuoteStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
            ->with(['party', 'recordedPayments', 'creditNotes', 'deductions'])
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

        $validated = Invoice::query()
            ->where('status', InvoiceStatus::Validated)
            ->with(['recordedPayments', 'creditNotes', 'deductions'])
            ->get();
        $summary = $validated->groupBy('currency')->map(fn ($invoices) => [
            'invoiced' => round((float) $invoices->sum('total'), 2),
            'collected' => round((float) $invoices->sum(fn (Invoice $invoice) => $invoice->paidAmount()), 2),
            'outstanding' => round((float) $invoices->sum(fn (Invoice $invoice) => $invoice->balanceDue()), 2),
            'overdue_count' => $invoices->filter(fn (Invoice $invoice) => $invoice->isOverdue())->count(),
            'overdue_amount' => round((float) $invoices->filter(fn (Invoice $invoice) => $invoice->isOverdue())->sum(fn (Invoice $invoice) => $invoice->balanceDue()), 2),
        ]);
        $counts = Invoice::query()->toBase()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return view('invoices.index', compact('invoices', 'search', 'status', 'summary', 'counts'));
    }

    public function show(Invoice $invoice): View
    {
        $this->requirePermission(Permission::InvoicesView);
        $invoice->load(['party', 'quote', 'lines', 'creator', 'payments.recorder', 'recordedPayments', 'creditNotes', 'deductions.payment']);
        $presenter = app(CommercialDocumentPresenter::class);
        $verifiable = $presenter->isVerifiable($invoice);

        return view('invoices.show', [
            'invoice' => $invoice,
            'fingerprint' => $verifiable ? $presenter->fingerprint($invoice) : null,
            'verificationUrl' => $verifiable ? $presenter->verificationUrl($invoice) : null,
            'validator' => $invoice->validated_by ? User::find($invoice->validated_by) : null,
            'canceller' => $invoice->cancelled_by ? User::find($invoice->cancelled_by) : null,
        ]);
    }

    public function print(Request $request, Invoice $invoice): View
    {
        $this->requirePermission(Permission::InvoicesView);

        return view('documents.commercial', $this->documentData($invoice, false, $this->printLanguage($request, $invoice)));
    }

    public function pdf(Request $request, Invoice $invoice): Response
    {
        $this->requirePermission(Permission::InvoicesView);
        $language = $this->printLanguage($request, $invoice);
        $filename = ($invoice->number ?: ($language === DocumentLanguage::English ? 'draft-invoice-' : 'facture-brouillon-').$invoice->id).'.pdf';

        return app(CommercialDocumentPresenter::class)
            ->download($this->documentData($invoice, true, $language), $filename);
    }

    public function convert(Quote $quote): RedirectResponse
    {
        $this->requirePermission(Permission::QuotesConvert);
        $this->requirePermission(Permission::InvoicesCreate);

        $invoice = DB::transaction(function () use ($quote) {
            $quote = Quote::query()->with(['lines', 'party'])->lockForUpdate()->findOrFail($quote->id);
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
                'due_date' => today()->addDays(CompanySetting::current()->default_payment_days),
                'currency' => $quote->currency,
                'language' => $quote->party->document_language ?? DocumentLanguage::French,
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

        $invoice->load('deductions');
        $treasuryAccounts = TreasuryAccount::query()
            ->where('is_active', true)
            ->where('currency', $invoice->currency)
            ->orderBy('name')
            ->get();

        return view('invoices.edit', compact('invoice', 'treasuryAccounts'));
    }

    public function update(InvoiceDraftRequest $request, Invoice $invoice, PaymentRecorder $recorder): RedirectResponse
    {
        $this->requirePermission(Permission::InvoicesUpdateDraft);
        $data = $request->validated();

        DB::transaction(function () use ($invoice, $data, $recorder) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $this->ensureDraft($invoice);
            $deductions = $this->buildDeductions($invoice, $data['deductions'] ?? [], $recorder);

            $invoice->update([
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'notes' => $data['notes'],
                'language' => $data['language'] ?? $invoice->language,
                'deductions_total' => round((float) collect($deductions)->sum('amount'), 2),
            ]);
            $invoice->deductions()->delete();
            $invoice->deductions()->createMany($deductions);
        });

        return to_route('invoices.show', $invoice)->with('success', 'La facture brouillon a été mise à jour.');
    }

    /**
     * Validation numbers the invoice, posts the sales entry and turns each
     * deducted advance into a recorded payment dated when it was received.
     */
    public function validateInvoice(Invoice $invoice, AccountingService $accounting, PaymentRecorder $recorder): RedirectResponse
    {
        $this->requirePermission(Permission::InvoicesValidate);
        $this->ensureDraft($invoice);
        if ($invoice->deductions()->where('type', DeductionType::Advance)->exists()) {
            $this->requirePermission(Permission::PaymentsRecord);
        }

        try {
            DB::transaction(function () use ($invoice, $accounting, $recorder) {
                $invoice = Invoice::query()->with('deductions')->lockForUpdate()->findOrFail($invoice->id);
                $this->ensureDraft($invoice);
                $invoice->update([
                    'number' => CompanySetting::current()->documentNumber('invoice', $invoice->id, $invoice->issue_date),
                    'status' => InvoiceStatus::Validated,
                    'validated_at' => now(),
                    'validated_by' => auth()->id(),
                ]);
                $accounting->postInvoice($invoice, auth()->id());

                foreach ($invoice->deductions->filter(fn (InvoiceDeduction $deduction) => $deduction->isAdvance()) as $advance) {
                    $payment = $recorder->record($invoice, [
                        'payment_date' => $advance->received_on,
                        'amount' => $advance->amount,
                        'method' => $advance->payment_method,
                        'treasury_account_id' => $advance->treasury_account_id,
                        'reference' => $advance->reference,
                        'notes' => "Avance déduite sur la facture {$invoice->number} : {$advance->description}",
                    ], auth()->id());
                    $advance->update(['payment_id' => $payment->id]);
                }
            });
        } catch (ValidationException $exception) {
            return back()->with('error', 'Avance non enregistrée : '.collect($exception->errors())->flatten()->first().' Corrigez la facture brouillon.');
        }

        return back()->with('success', 'La facture a été validée et numérotée.');
    }

    /**
     * A validated invoice can only be cancelled while nothing has been
     * settled against it; its sales entry is then reversed.
     */
    public function cancel(Invoice $invoice, AccountingService $accounting): RedirectResponse
    {
        $this->requirePermission(Permission::InvoicesCancel);

        $blocker = DB::transaction(function () use ($invoice, $accounting) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            abort_if($invoice->status === InvoiceStatus::Cancelled, 409, 'Cette facture est déjà annulée.');

            if ($blocker = $invoice->cancellationBlocker()) {
                return $blocker;
            }

            $invoice->update([
                'status' => InvoiceStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => auth()->id(),
            ]);
            $accounting->reverseInvoice($invoice, auth()->id());

            return null;
        });

        return $blocker
            ? back()->with('error', $blocker)
            : back()->with('success', 'La facture a été annulée et son écriture comptable contrepassée.');
    }

    private function ensureDraft(Invoice $invoice): void
    {
        abort_unless($invoice->status === InvoiceStatus::Draft, 409, 'Seule une facture brouillon peut être modifiée.');
    }

    /**
     * Amounts are always recomputed here: only quantity and unit price are
     * trusted from the form.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function buildDeductions(Invoice $invoice, array $rows, PaymentRecorder $recorder): array
    {
        $deductions = [];
        foreach (array_values($rows) as $index => $row) {
            $type = DeductionType::from($row['type']);
            $isAdvance = $type === DeductionType::Advance;
            $treasuryAccount = null;

            if ($isAdvance) {
                try {
                    $treasuryAccount = $recorder->resolveTreasuryAccount($invoice, $row['treasury_account_id'] ?? null);
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages([
                        "deductions.{$index}.treasury_account_id" => collect($exception->errors())->flatten()->first(),
                    ]);
                }
            }

            $deductions[] = [
                'position' => $index + 1,
                'type' => $type,
                'description' => $row['description'],
                'quantity' => $row['quantity'],
                'unit_price' => $row['unit_price'],
                'amount' => round((float) $row['quantity'] * (float) $row['unit_price'], 2),
                'received_on' => $isAdvance ? $row['received_on'] : null,
                'payment_method' => $isAdvance ? $row['payment_method'] : null,
                'treasury_account_id' => $treasuryAccount?->id,
                'reference' => $isAdvance ? $row['reference'] : null,
            ];
        }

        $total = round((float) collect($deductions)->sum('amount'), 2);
        if ($total > (float) $invoice->total) {
            throw ValidationException::withMessages([
                'deductions' => 'Les déductions ('.number_format($total, 2, ',', ' ').') dépassent le total TTC de la facture ('.number_format((float) $invoice->total, 2, ',', ' ').').',
            ]);
        }

        return $deductions;
    }

    /**
     * The invoice is printed in its own language unless another one is asked
     * for: the language changes neither the amounts nor the control code.
     */
    private function printLanguage(Request $request, Invoice $invoice): DocumentLanguage
    {
        return DocumentLanguage::tryFrom((string) $request->query('lang'))
            ?? $invoice->language
            ?? DocumentLanguage::French;
    }

    private function documentData(Invoice $invoice, bool $forPdf, DocumentLanguage $language): array
    {
        $locale = $language->value;
        $invoice->load(['party', 'lines', 'quote', 'creator', 'deductions', 'recordedPayments', 'creditNotes']);

        return [
            ...app(CommercialDocumentPresenter::class)->present($invoice, $language),
            'document' => $invoice,
            'documentType' => __('document.invoice', [], $locale),
            'documentNumber' => $invoice->number ?: __('document.draft_number', ['id' => $invoice->id], $locale),
            'statusLabel' => __("document.invoice_status.{$invoice->status->value}", [], $locale),
            'secondaryDateLabel' => __('document.due_date', [], $locale),
            'secondaryDate' => $invoice->due_date,
            'reference' => $invoice->quote ? [__('document.origin_quote', [], $locale), $invoice->quote->number] : null,
            'settlement' => $invoice->status === InvoiceStatus::Validated ? [
                'credited' => $invoice->creditedAmount(),
                // Advances already appear among the deductions.
                'paid' => round($invoice->paidAmount() - $invoice->advancePaidAmount(), 2),
                'balance' => $invoice->balanceDue(),
            ] : null,
            'backUrl' => route('invoices.show', $invoice),
            'pdfUrl' => route('invoices.pdf', [$invoice, 'lang' => $locale]),
            'forPdf' => $forPdf,
        ];
    }

    private function requirePermission(Permission $permission): void
    {
        abort_unless(auth()->user()->can($permission->value), 403);
    }
}
