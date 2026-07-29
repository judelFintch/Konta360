<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountingEntry;
use App\Models\Journal;
use App\Modules\Accounting\Enums\EntryStatus;
use App\Modules\Accounting\Services\AccountingService;
use App\Modules\Administration\Enums\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountingEntryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth()->user()->can(Permission::AccountingView->value), 403);
        $search = trim((string) $request->query('search'));
        $journal = $request->query('journal');
        $currency = $request->query('currency');

        $entries = AccountingEntry::query()
            ->with('journal')
            ->withSum('lines as debit_total', 'debit')
            ->withSum('lines as credit_total', 'credit')
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('number', 'like', "%{$search}%")
                    ->orWhere('label', 'like', "%{$search}%");
            }))
            ->when($journal, fn ($query) => $query->whereHas('journal', fn ($query) => $query->where('code', $journal)))
            ->when(in_array($currency, ['CDF', 'USD'], true), fn ($query) => $query->where('currency', $currency))
            ->latest('entry_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $journals = Journal::query()->where('is_active', true)->orderBy('code')->get();

        return view('accounting.entries.index', compact('entries', 'journals', 'search', 'journal', 'currency'));
    }

    public function show(AccountingEntry $entry): View
    {
        abort_unless(auth()->user()->can(Permission::AccountingView->value), 403);
        $entry->load(['journal', 'lines.account', 'creator']);

        return view('accounting.entries.show', compact('entry'));
    }

    public function create(): View
    {
        abort_unless(auth()->user()->can(Permission::AccountingEntriesCreate->value), 403);

        return view('accounting.entries.create', [
            'journals' => Journal::query()->where('is_active', true)->orderBy('code')->get(),
            'accounts' => Account::query()->where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can(Permission::AccountingEntriesCreate->value), 403);
        $data = $request->validate([
            'journal_id' => ['required', 'integer', 'exists:journals,id'],
            'entry_date' => ['required', 'date'],
            'label' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'in:CDF,USD'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
        ]);

        $lines = collect($data['lines'])->map(function (array $line, int $index) {
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);
            if (($debit <= 0 && $credit <= 0) || ($debit > 0 && $credit > 0)) {
                throw ValidationException::withMessages([
                    "lines.{$index}.debit" => 'Chaque ligne doit contenir soit un débit, soit un crédit.',
                ]);
            }

            return [...$line, 'position' => $index + 1, 'debit' => $debit, 'credit' => $credit];
        });
        $debit = round((float) $lines->sum('debit'), 2);
        $credit = round((float) $lines->sum('credit'), 2);
        if ($debit <= 0 || abs($debit - $credit) > 0.001) {
            throw ValidationException::withMessages([
                'lines' => 'L’écriture doit être équilibrée : le total débit doit être égal au total crédit.',
            ]);
        }

        $entry = DB::transaction(function () use ($data, $lines) {
            $entry = AccountingEntry::create([
                'journal_id' => $data['journal_id'],
                'entry_date' => $data['entry_date'],
                'label' => trim($data['label']),
                'currency' => $data['currency'],
                'status' => EntryStatus::Draft,
                'created_by' => auth()->id(),
            ]);
            $entry->update(['number' => sprintf('ECR-%s-%06d', $entry->entry_date->format('Y'), $entry->id)]);
            $entry->lines()->createMany($lines->all());

            return $entry;
        });

        return to_route('accounting.entries.show', $entry)->with('success', 'L’écriture brouillon a été créée.');
    }

    public function post(AccountingEntry $entry, AccountingService $accounting): RedirectResponse
    {
        abort_unless(auth()->user()->can(Permission::AccountingEntriesPost->value), 403);
        abort_unless($entry->status === EntryStatus::Draft, 409, 'Cette écriture est déjà comptabilisée.');

        DB::transaction(fn () => $accounting->postManualEntry($entry, auth()->id()));

        return back()->with('success', 'L’écriture a été comptabilisée.');
    }
}
