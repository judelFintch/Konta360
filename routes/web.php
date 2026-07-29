<?php

use App\Http\Controllers\CatalogItemController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\QuoteController;
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

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('quotes', QuoteController::class)
        ->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::get('quotes/{quote}/print', [QuoteController::class, 'print'])->name('quotes.print');
    Route::get('quotes/{quote}/pdf', [QuoteController::class, 'pdf'])->name('quotes.pdf');
    Route::patch('quotes/{quote}/send', [QuoteController::class, 'markAsSent'])->name('quotes.send');

    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::get('invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
    Route::put('invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
    Route::post('quotes/{quote}/invoice', [InvoiceController::class, 'convert'])->name('quotes.invoice');
    Route::patch('invoices/{invoice}/validate', [InvoiceController::class, 'validateInvoice'])->name('invoices.validate');
    Route::patch('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
});

require __DIR__.'/auth.php';
