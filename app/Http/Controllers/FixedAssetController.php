<?php

namespace App\Http\Controllers;

use App\Http\Requests\FixedAssetRequest;
use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciation;
use App\Models\Party;
use App\Modules\Accounting\Services\AccountingService;
use App\Modules\FixedAssets\Enums\AssetStatus;
use App\Modules\FixedAssets\Services\DepreciationSchedule;
use App\Modules\Parties\Enums\PartyType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FixedAssetController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $status = $request->query('status');

        $assets = FixedAsset::query()
            ->with('supplier')
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            }))
            ->when(
                in_array($status, array_column(AssetStatus::cases(), 'value'), true),
                fn ($query) => $query->where('status', $status)
            )
            ->latest('in_service_date')
            ->paginate(15)
            ->withQueryString();

        return view('fixed-assets.index', compact('assets', 'search', 'status'));
    }

    public function create(): View
    {
        return view('fixed-assets.create', ['suppliers' => $this->suppliers()]);
    }

    public function store(FixedAssetRequest $request): RedirectResponse
    {
        $asset = FixedAsset::create([
            ...$request->validated(),
            'status' => AssetStatus::Active,
            'created_by' => auth()->id(),
        ]);
        $asset->update(['code' => sprintf('IMM-%s-%05d', $asset->in_service_date->format('Y'), $asset->id)]);

        return to_route('fixed-assets.show', $asset)->with('success', 'L’immobilisation a été créée.');
    }

    public function show(FixedAsset $fixedAsset, DepreciationSchedule $calculator): View
    {
        $fixedAsset->load(['supplier', 'depreciations.accountingEntry']);
        $schedule = $calculator->for($fixedAsset);
        $accumulated = round($schedule->where('period', '<=', today())->sum('depreciation'), 2);
        $postedAccumulated = round((float) $fixedAsset->depreciations->sum('amount'), 2);

        return view('fixed-assets.show', [
            'asset' => $fixedAsset,
            'schedule' => $schedule,
            'accumulated' => $accumulated,
            'netBookValue' => round((float) $fixedAsset->acquisition_cost - $accumulated, 2),
            'postedAccumulated' => $postedAccumulated,
            'accountingNetBookValue' => round((float) $fixedAsset->acquisition_cost - $postedAccumulated, 2),
            'postedPeriods' => $fixedAsset->depreciations->keyBy(fn ($depreciation) => $depreciation->period_date->format('Y-m-d')),
        ]);
    }

    public function edit(FixedAsset $fixedAsset): View
    {
        return view('fixed-assets.edit', [
            'asset' => $fixedAsset,
            'suppliers' => $this->suppliers(),
        ]);
    }

    public function update(FixedAssetRequest $request, FixedAsset $fixedAsset): RedirectResponse
    {
        $fixedAsset->update($request->validated());

        return to_route('fixed-assets.show', $fixedAsset)->with('success', 'L’immobilisation a été mise à jour.');
    }

    public function postDepreciations(
        Request $request,
        FixedAsset $fixedAsset,
        DepreciationSchedule $calculator,
        AccountingService $accounting
    ): RedirectResponse {
        $data = $request->validate([
            'through_date' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $postedCount = DB::transaction(function () use ($fixedAsset, $calculator, $accounting, $data) {
            $fixedAsset = FixedAsset::query()->lockForUpdate()->findOrFail($fixedAsset->id);
            $throughDate = Carbon::parse($data['through_date'])->endOfDay();
            $existingPeriods = $fixedAsset->depreciations()
                ->pluck('period_date')
                ->map(fn ($date) => Carbon::parse($date)->format('Y-m-d'));
            $dueRows = $calculator->for($fixedAsset)
                ->filter(fn (array $row) => $row['period']->lte($throughDate))
                ->reject(fn (array $row) => $existingPeriods->contains($row['period']->format('Y-m-d')));

            if ($dueRows->isEmpty()) {
                throw ValidationException::withMessages([
                    'through_date' => 'Aucune nouvelle dotation n’est due à cette date.',
                ]);
            }

            foreach ($dueRows as $row) {
                $depreciation = FixedAssetDepreciation::create([
                    'fixed_asset_id' => $fixedAsset->id,
                    'period_date' => $row['period'],
                    'amount' => $row['depreciation'],
                    'posted_at' => now(),
                    'posted_by' => auth()->id(),
                ]);
                $entry = $accounting->postDepreciation($depreciation, auth()->id());
                $depreciation->update(['accounting_entry_id' => $entry->id]);
            }

            return $dueRows->count();
        });

        return back()->with('success', "{$postedCount} dotation(s) d’amortissement comptabilisée(s).");
    }

    private function suppliers()
    {
        return Party::query()
            ->where('is_active', true)
            ->whereIn('type', [PartyType::Supplier, PartyType::Both])
            ->orderBy('name')
            ->get();
    }
}
