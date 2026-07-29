<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountingPeriodRequest;
use App\Models\AccountingEntry;
use App\Models\AccountingPeriod;
use App\Modules\Accounting\Enums\EntryStatus;
use App\Modules\Accounting\Enums\PeriodStatus;
use App\Modules\Administration\Enums\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AccountingPeriodController extends Controller
{
    public function index(): View
    {
        $this->authorizeManagement();

        $periods = AccountingPeriod::query()->with('closer')->latest('starts_on')->get();

        return view('accounting.periods.index', compact('periods'));
    }

    public function store(AccountingPeriodRequest $request): RedirectResponse
    {
        $this->authorizeManagement();
        AccountingPeriod::create([
            ...$request->validated(),
            'status' => PeriodStatus::Open,
        ]);

        return back()->with('success', 'La période comptable a été créée.');
    }

    public function close(AccountingPeriod $period): RedirectResponse
    {
        $this->authorizeManagement();

        DB::transaction(function () use ($period) {
            $period = AccountingPeriod::query()->lockForUpdate()->findOrFail($period->id);
            abort_unless($period->status === PeriodStatus::Open, 409, 'Cette période est déjà clôturée.');
            abort_if($period->ends_on->isFuture(), 409, 'Une période ne peut être clôturée avant sa date de fin.');

            $draftEntries = AccountingEntry::query()
                ->where('status', EntryStatus::Draft)
                ->whereBetween('entry_date', [$period->starts_on, $period->ends_on])
                ->count();
            abort_if($draftEntries > 0, 409, 'Toutes les écritures de la période doivent être comptabilisées avant la clôture.');

            $period->update([
                'status' => PeriodStatus::Closed,
                'closed_at' => now(),
                'closed_by' => auth()->id(),
            ]);
        });

        return back()->with('success', 'La période est clôturée. Toute nouvelle écriture sur cette période est maintenant bloquée.');
    }

    private function authorizeManagement(): void
    {
        abort_unless(auth()->user()->can(Permission::AccountingPeriodsClose->value), 403);
    }
}
