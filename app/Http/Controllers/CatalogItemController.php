<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogItemRequest;
use App\Models\CatalogItem;
use App\Modules\Catalog\Enums\ItemType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogItemController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $type = $request->query('type');

        $items = CatalogItem::query()
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }))
            ->when(
                in_array($type, array_column(ItemType::cases(), 'value'), true),
                fn ($query) => $query->where('type', $type)
            )
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('catalog.index', compact('items', 'search', 'type'));
    }

    public function create(): View
    {
        return view('catalog.create');
    }

    public function store(CatalogItemRequest $request): RedirectResponse
    {
        CatalogItem::create($request->validated());

        return to_route('catalog.index')->with('success', 'L’article a été créé.');
    }

    public function edit(CatalogItem $catalogItem): View
    {
        return view('catalog.edit', compact('catalogItem'));
    }

    public function update(CatalogItemRequest $request, CatalogItem $catalogItem): RedirectResponse
    {
        $catalogItem->update($request->validated());

        return to_route('catalog.index')->with('success', 'L’article a été mis à jour.');
    }
}
