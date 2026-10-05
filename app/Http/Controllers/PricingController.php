<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\View\View;

/**
 * Public page comparing the plans. Built from the plans table, so prices and
 * limits changed from the platform area show here at once.
 */
class PricingController extends Controller
{
    public function __invoke(): View
    {
        return view('pricing', [
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'durations' => SubscriptionController::DURATIONS,
        ]);
    }
}
