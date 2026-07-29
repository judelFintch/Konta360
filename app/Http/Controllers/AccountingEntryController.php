<?php

namespace App\Http\Controllers;

use App\Models\AccountingEntry;
use App\Models\Journal;
use App\Modules\Administration\Enums\Permission;
use Illuminate\Http\Request;
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
}
