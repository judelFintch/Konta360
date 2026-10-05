<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Prices and limits of the plans. A change applies to every company on the
 * plan; payments already declared keep the amount computed at the time.
 */
class PlanController extends Controller
{
    public function index(): View
    {
        return view('platform.plans.index', ['plans' => Plan::query()->withCount('companies')->orderBy('sort_order')->get()]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'monthly_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'currency' => ['required', 'in:USD,CDF'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'max_invoices_per_month' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
        ]);

        $plan->update($data);

        return back()->with('success', "La formule {$plan->name} a été mise à jour.");
    }
}
