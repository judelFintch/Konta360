<?php

use App\Models\BankReconciliation;
use App\Models\TreasuryAccount;
use App\Models\TreasuryTransaction;
use App\Models\User;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\Treasury\Enums\BankReconciliationStatus;
use App\Modules\Treasury\Enums\TreasuryAccountType;
use App\Modules\Treasury\Enums\TreasuryFlowNature;
use App\Modules\Treasury\Enums\TreasuryTransactionType;
use App\Modules\Treasury\Services\TreasuryReportBuilder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole(Role::Comptable->value);

    $this->bank = TreasuryAccount::create([
        'name' => 'Banque USD', 'type' => TreasuryAccountType::Bank, 'currency' => 'USD',
        'opening_balance' => 1000, 'is_active' => true, 'created_by' => $this->user->id,
    ]);
    $this->cash = TreasuryAccount::create([
        'name' => 'Caisse USD', 'type' => TreasuryAccountType::Cash, 'currency' => 'USD',
        'opening_balance' => 100, 'is_active' => true, 'created_by' => $this->user->id,
    ]);

    $movement = fn (array $data) => TreasuryTransaction::create([
        'currency' => 'USD', 'created_by' => $this->user->id, ...$data,
    ]);
    // Before the period: brought forward.
    $movement(['number' => 'TRES-01', 'treasury_account_id' => $this->bank->id, 'type' => TreasuryTransactionType::Inflow,
        'transaction_date' => '2026-06-20', 'amount' => 500, 'description' => 'Apport', 'source_type' => null]);
    // Inside the period.
    $this->receipt = $movement(['number' => 'TRES-02', 'treasury_account_id' => $this->bank->id, 'type' => TreasuryTransactionType::Inflow,
        'transaction_date' => '2026-07-05', 'amount' => 300, 'description' => 'Règlement FAC-1', 'source_type' => 'payment', 'source_id' => 1]);
    $this->supplier = $movement(['number' => 'TRES-03', 'treasury_account_id' => $this->bank->id, 'type' => TreasuryTransactionType::Outflow,
        'transaction_date' => '2026-07-08', 'amount' => 120, 'description' => 'Fournisseur X', 'source_type' => 'expense_payment', 'source_id' => 1]);
    $this->transfer = $movement(['number' => 'TRES-04', 'treasury_account_id' => $this->bank->id, 'destination_account_id' => $this->cash->id,
        'type' => TreasuryTransactionType::Transfer, 'transaction_date' => '2026-07-10', 'amount' => 200, 'description' => 'Alimentation caisse']);
    $movement(['number' => 'TRES-05', 'treasury_account_id' => $this->cash->id, 'type' => TreasuryTransactionType::Outflow,
        'transaction_date' => '2026-08-02', 'amount' => 30, 'description' => 'Carburant']);
});

it('builds a cash journal with balance brought forward and running balance', function () {
    $statement = app(TreasuryReportBuilder::class)->statement($this->bank, '2026-07-01', '2026-07-31');

    expect($statement['opening'])->toBe(1500.0)
        ->and($statement['lines']->pluck('balance')->all())->toBe([1800.0, 1680.0, 1480.0])
        ->and($statement['lines']->pluck('nature')->all())->toBe([
            TreasuryFlowNature::CustomerReceipt, TreasuryFlowNature::SupplierPayment, TreasuryFlowNature::InternalTransfer,
        ])
        ->and($statement['lines'][2]['counterpart'])->toBe('→ Caisse USD')
        ->and($statement['receipts'])->toBe(300.0)
        ->and($statement['payments'])->toBe(320.0)
        ->and($statement['closing'])->toBe(1480.0)
        ->and($statement['closing'])->toBe(app(TreasuryReportBuilder::class)->balanceAt($this->bank, '2026-07-31'));

    $cash = app(TreasuryReportBuilder::class)->statement($this->cash, '2026-07-01', '2026-08-31');
    expect($cash['opening'])->toBe(100.0)
        ->and($cash['lines']->pluck('balance')->all())->toBe([300.0, 270.0])
        ->and($cash['closing'])->toBe($this->cash->balance());
});

it('presents the cash position per currency with subtotals', function () {
    $usd = app(TreasuryReportBuilder::class)->position('2026-07-01', '2026-07-31')->get('USD');

    expect($usd['total'])->toBe(['opening' => 1600.0, 'receipts' => 500.0, 'payments' => 320.0, 'closing' => 1780.0])
        ->and($usd['subtotals']['bank']['closing'])->toBe(1480.0)
        ->and($usd['subtotals']['cash']['closing'])->toBe(300.0);
});

it('neutralises internal transfers in the receipts and payments statement', function () {
    $flows = app(TreasuryReportBuilder::class)->flows('2026-07-01', '2026-08-31', 'USD');

    expect($flows['opening'])->toBe(1600.0)
        ->and($flows['totalReceipts'])->toBe(300.0)
        ->and($flows['totalPayments'])->toBe(150.0)
        ->and($flows['transfers'])->toBe(['amount' => 200.0, 'count' => 1])
        ->and($flows['closing'])->toBe(1750.0)
        ->and($flows['closingControl'])->toBe(1750.0)
        ->and($flows['monthly']->pluck('closing')->all())->toBe([1780.0, 1750.0]);
});

it('lists outstanding items in the bank reconciliation statement', function () {
    $reconciliation = BankReconciliation::create([
        'number' => 'RAP-2026-00001', 'treasury_account_id' => $this->bank->id,
        'starts_on' => '2026-07-01', 'ends_on' => '2026-07-31',
        'statement_opening_balance' => 1500, 'statement_closing_balance' => 1800,
        'calculated_closing_balance' => 1800, 'difference' => 0, 'currency' => 'USD',
        'status' => BankReconciliationStatus::Completed, 'created_by' => $this->user->id,
        'completed_at' => now(), 'completed_by' => $this->user->id,
    ]);
    $reconciliation->transactions()->attach([$this->receipt->id]);
    $older = TreasuryTransaction::where('number', 'TRES-01')->first();
    $reconciliation->transactions()->attach([$older->id]);
    $reconciliation->update(['statement_opening_balance' => 1000]);

    $state = app(TreasuryReportBuilder::class)->reconciliation($reconciliation->fresh());

    expect($state['bookBalance'])->toBe(1480.0)
        ->and($state['outstandingPayments']->pluck('transaction.number')->all())->toBe(['TRES-03', 'TRES-04'])
        ->and($state['outstandingReceipts'])->toBeEmpty()
        ->and($state['adjustedBook'])->toBe(1800.0)
        ->and($state['residual'])->toBe(0.0);
});

it('prints every treasury report as a real pdf', function (string $route, array $parameters) {
    $parameters = array_map(fn ($value) => is_callable($value) ? $value($this) : $value, $parameters);
    $response = $this->actingAs($this->user)->get(route($route, $parameters));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF');
})->with([
    'journal (all accounts)' => ['treasury.reports.journal', ['date_from' => '2026-07-01', 'date_to' => '2026-07-31']],
    'journal (one account)' => ['treasury.reports.journal', ['account_id' => fn ($test) => $test->bank->id, 'date_from' => '2026-07-01', 'date_to' => '2026-07-31']],
    'position' => ['treasury.reports.position', ['date_from' => '2026-07-01', 'date_to' => '2026-07-31']],
    'flows' => ['treasury.reports.flows', ['currency' => 'USD', 'date_from' => '2026-01-01', 'date_to' => '2026-08-31']],
    'cash count' => ['treasury.reports.cash-count', ['account_id' => fn ($test) => $test->cash->id, 'date' => '2026-08-02']],
]);

it('prints the bank reconciliation statement', function () {
    $reconciliation = BankReconciliation::create([
        'number' => 'RAP-2026-00002', 'treasury_account_id' => $this->bank->id,
        'starts_on' => '2026-07-01', 'ends_on' => '2026-07-31',
        'statement_opening_balance' => 1500, 'statement_closing_balance' => 1800,
        'calculated_closing_balance' => 1800, 'difference' => 0, 'currency' => 'USD',
        'status' => BankReconciliationStatus::Completed, 'created_by' => $this->user->id,
        'completed_at' => now(), 'completed_by' => $this->user->id,
    ]);
    $reconciliation->transactions()->attach([$this->receipt->id]);

    $response = $this->actingAs($this->user)->get(route('treasury.reconciliations.pdf', $reconciliation));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF');
    $this->actingAs($this->user)->get(route('treasury.reconciliations.show', $reconciliation))
        ->assertSee('Imprimer l’état de rapprochement', false);
});

it('shows the reports page and restricts the cash count to cash accounts', function () {
    $this->actingAs($this->user)->get(route('treasury.reports.index'))
        ->assertOk()
        ->assertSee('Journal de caisse / de banque')
        ->assertSee('Procès-verbal d’arrêté de caisse', false);

    $this->actingAs($this->user)
        ->get(route('treasury.reports.cash-count', ['account_id' => $this->bank->id]))
        ->assertSessionHasErrors('account_id');
});

it('requires treasury and export permissions', function () {
    $commercial = User::factory()->create();
    $commercial->assignRole(Role::Commercial->value);
    $direction = User::factory()->create();
    $direction->assignRole(Role::Direction->value);

    foreach ([$commercial, $direction] as $user) {
        $this->actingAs($user)->get(route('treasury.reports.index'))->assertForbidden();
        $this->actingAs($user)->get(route('treasury.reports.journal'))->assertForbidden();
        $this->actingAs($user)->get(route('treasury.reports.position'))->assertForbidden();
    }
});
