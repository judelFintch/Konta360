<?php

namespace App\Http\Controllers;

use App\Http\Requests\FixedAssetRequest;
use App\Models\FixedAsset;
use App\Models\Party;
use App\Modules\FixedAssets\Enums\AssetStatus;
use App\Modules\FixedAssets\Services\DepreciationSchedule;
use App\Modules\Parties\Enums\PartyType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $fixedAsset->load('supplier');
        $schedule = $calculator->for($fixedAsset);
        $accumulated = round($schedule->where('period', '<=', today())->sum('depreciation'), 2);

        return view('fixed-assets.show', [
            'asset' => $fixedAsset,
            'schedule' => $schedule,
            'accumulated' => $accumulated,
            'netBookValue' => round((float) $fixedAsset->acquisition_cost - $accumulated, 2),
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

    private function suppliers()
    {
        return Party::query()
            ->where('is_active', true)
            ->whereIn('type', [PartyType::Supplier, PartyType::Both])
            ->orderBy('name')
            ->get();
    }
}
