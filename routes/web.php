<?php

use App\Http\Controllers\CatalogItemController;
use App\Http\Controllers\PartyController;
use App\Modules\Administration\Enums\Permission;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::resource('parties', PartyController::class)
    ->only(['index', 'create', 'store', 'edit', 'update'])
    ->middleware(['auth', 'verified', 'permission:'.Permission::PartiesManage->value]);

Route::resource('catalog', CatalogItemController::class)
    ->parameters(['catalog' => 'catalog_item'])
    ->only(['index', 'create', 'store', 'edit', 'update'])
    ->middleware(['auth', 'verified', 'permission:'.Permission::CatalogManage->value]);

require __DIR__.'/auth.php';
