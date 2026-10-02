<?php

namespace App\Http\Controllers;

use App\Models\AccountingEntry;
use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\TreasuryTransaction;
use App\Modules\Administration\Enums\Permission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function invoices(Request $request): StreamedResponse
    {
        $this->authorizeExport(Permission::InvoicesView);
        $query = Invoice::query()->with('party')->with(['payments', 'creditNotes']);
        $this->dates($query, $request, 'issue_date');

        return $this->csv('factures.csv', [
            'Numéro', 'Date', 'Échéance', 'Client', 'Statut', 'Total', 'Payé', 'Avoirs', 'Solde', 'Devise',
        ], $query->orderBy('issue_date')->cursor()->map(fn (Invoice $invoice) => [
            $invoice->number ?: 'Brouillon #'.$invoice->id,
            $invoice->issue_date->format('d/m/Y'),
            $invoice->due_date->format('d/m/Y'),
            $invoice->party->name,
            $invoice->status->label(),
            $invoice->total,
            $invoice->paidAmount(),
            $invoice->creditedAmount(),
            $invoice->balanceDue(),
            $invoice->currency,
        ]));
    }

    public function expenses(Request $request): StreamedResponse
    {
        $this->authorizeExport(Permission::AccountingView);
        $query = Expense::query()->with('supplier');
        $this->dates($query, $request, 'expense_date');

        return $this->csv('depenses-fournisseurs.csv', [
            'Numéro', 'Date', 'Échéance', 'Fournisseur', 'Référence fournisseur',
            'Description', 'Statut', 'HT', 'Taxe', 'Total', 'Payé', 'Solde', 'Devise',
        ], $query->orderBy('expense_date')->cursor()->map(fn (Expense $expense) => [
            $expense->number,
            $expense->expense_date->format('d/m/Y'),
            $expense->due_date->format('d/m/Y'),
            $expense->supplier?->name,
            $expense->supplier_reference,
            $expense->description,
            $expense->status->label(),
            $expense->subtotal,
            $expense->tax_total,
            $expense->total,
            $expense->paidAmount(),
            $expense->balanceDue(),
            $expense->currency,
        ]));
    }

    public function accountingEntries(Request $request): StreamedResponse
    {
        $this->authorizeExport(Permission::AccountingView);
        $query = AccountingEntry::query()->with(['journal', 'lines.account']);
        $this->dates($query, $request, 'entry_date');
        $rows = $query->orderBy('entry_date')->cursor()->flatMap(fn (AccountingEntry $entry) => $entry->lines->map(fn ($line) => [
            $entry->number,
            $entry->entry_date->format('d/m/Y'),
            $entry->journal->code,
            $entry->label,
            $entry->status->label(),
            $line->account->code,
            $line->account->name,
            $line->description,
            $line->debit,
            $line->credit,
            $entry->currency,
        ]));

        return $this->csv('journal-comptable.csv', [
            'Écriture', 'Date', 'Journal', 'Libellé général', 'Statut', 'Compte',
            'Nom du compte', 'Libellé ligne', 'Débit', 'Crédit', 'Devise',
        ], $rows);
    }

    public function treasury(Request $request): StreamedResponse
    {
        $this->authorizeExport(Permission::TreasuryManage);
        $query = TreasuryTransaction::query()->with(['account', 'destinationAccount']);
        $this->dates($query, $request, 'transaction_date');

        return $this->csv('mouvements-tresorerie.csv', [
            'Numéro', 'Date', 'Type', 'Compte source', 'Compte destinataire',
            'Description', 'Référence', 'Montant', 'Devise',
        ], $query->orderBy('transaction_date')->cursor()->map(fn (TreasuryTransaction $transaction) => [
            $transaction->number,
            $transaction->transaction_date->format('d/m/Y'),
            $transaction->type->label(),
            $transaction->account->name,
            $transaction->destinationAccount?->name,
            $transaction->description,
            $transaction->reference,
            $transaction->amount,
            $transaction->currency,
        ]));
    }

    public function audit(Request $request): StreamedResponse
    {
        $this->authorizeExport(Permission::AuditView);
        $query = AuditLog::query()->with('user');
        $this->dates($query, $request, 'created_at');

        return $this->csv('journal-audit.csv', [
            'Date', 'Utilisateur', 'Action', 'Description', 'Route', 'Méthode',
            'Type objet', 'ID objet', 'Adresse IP',
        ], $query->orderBy('created_at')->cursor()->map(fn (AuditLog $log) => [
            $log->created_at->format('d/m/Y H:i:s'),
            $log->user?->name,
            $log->action,
            $log->description,
            $log->route_name,
            $log->http_method,
            $log->subject_type ? class_basename($log->subject_type) : null,
            $log->subject_id,
            $log->ip_address,
        ]));
    }

    private function authorizeExport(Permission $domainPermission): void
    {
        abort_unless(
            auth()->user()->can(Permission::ReportsExport->value)
            && auth()->user()->can($domainPermission->value),
            403
        );
    }

    private function dates(Builder $query, Request $request, string $column): void
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $query->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate($column, '>=', $date))
            ->when($data['to'] ?? null, fn (Builder $query, string $date) => $query->whereDate($column, '<=', $date));
    }

    private function csv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers, ';');
            foreach ($rows as $row) {
                fputcsv($output, array_map($this->sanitize(...), $row), ';');
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function sanitize(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }
}
