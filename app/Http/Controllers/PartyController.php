<?php

namespace App\Http\Controllers;

use App\Http\Requests\PartyRequest;
use App\Models\Party;
use App\Modules\Parties\Enums\PartyType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PartyController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $type = $request->query('type');

        $parties = Party::query()
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('tax_identifier', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when(
                in_array($type, array_column(PartyType::cases(), 'value'), true),
                fn ($query) => $query->where('type', $type)
            )
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('parties.index', compact('parties', 'search', 'type'));
    }

    public function create(): View
    {
        return view('parties.create');
    }

    public function store(PartyRequest $request): RedirectResponse
    {
        Party::create($request->validated());

        return to_route('parties.index')->with('success', 'Le tiers a été créé.');
    }

    public function edit(Party $party): View
    {
        return view('parties.edit', compact('party'));
    }

    public function update(PartyRequest $request, Party $party): RedirectResponse
    {
        $party->update($request->validated());

        return to_route('parties.index')->with('success', 'Le tiers a été mis à jour.');
    }
}
