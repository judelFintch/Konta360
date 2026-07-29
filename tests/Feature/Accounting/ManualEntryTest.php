<?php

use App\Models\Account;
use App\Models\AccountingEntry;
use App\Models\AccountingPeriod;
use App\Models\Journal;
use App\Models\User;
use App\Modules\Accounting\Enums\EntryStatus;
use App\Modules\Accounting\Enums\PeriodStatus;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole(Role::Comptable->value);
    $this->journal = Journal::where('code', 'OD')->firstOrFail();
    $this->expense = Account::where('code', '65')->firstOrFail();
    $this->bank = Account::where('code', '512')->firstOrFail();
    $this->payload = [
        'journal_id' => $this->journal->id,
        'entry_date' => today()->format('Y-m-d'),
        'label' => 'Frais administratifs',
        'currency' => 'USD',
        'lines' => [
            ['account_id' => $this->expense->id, 'description' => 'Charge', 'debit' => 250, 'credit' => 0],
            ['account_id' => $this->bank->id, 'description' => 'Banque', 'debit' => 0, 'credit' => 250],
        ],
    ];
});

it('creates a balanced manual entry as a draft', function () {
    $this->actingAs($this->user)
        ->post(route('accounting.entries.store'), $this->payload)
        ->assertRedirect();

    $entry = AccountingEntry::with('lines')->firstOrFail();

    expect($entry->number)->toMatch('/^ECR-\d{4}-\d{6}$/')
        ->and($entry->status)->toBe(EntryStatus::Draft)
        ->and($entry->lines)->toHaveCount(2)
        ->and($entry->totalDebit())->toBe(250.0)
        ->and($entry->totalCredit())->toBe(250.0);
});

it('rejects an unbalanced entry and lines containing both sides', function () {
    $unbalanced = $this->payload;
    $unbalanced['lines'][1]['credit'] = 200;
    $this->actingAs($this->user)
        ->post(route('accounting.entries.store'), $unbalanced)
        ->assertSessionHasErrors('lines');

    $invalidLine = $this->payload;
    $invalidLine['lines'][0]['credit'] = 250;
    $this->actingAs($this->user)
        ->post(route('accounting.entries.store'), $invalidLine)
        ->assertSessionHasErrors('lines.0.debit');

    expect(AccountingEntry::count())->toBe(0);
});

it('posts a draft manual entry', function () {
    $this->actingAs($this->user)->post(route('accounting.entries.store'), $this->payload);
    $entry = AccountingEntry::firstOrFail();

    $this->actingAs($this->user)
        ->patch(route('accounting.entries.post', $entry))
        ->assertRedirect();

    expect($entry->refresh())
        ->status->toBe(EntryStatus::Posted)
        ->posted_by->toBe($this->user->id)
        ->posted_at->not->toBeNull();
});

it('does not post into a closed accounting period', function () {
    $this->actingAs($this->user)->post(route('accounting.entries.store'), $this->payload);
    $entry = AccountingEntry::firstOrFail();
    AccountingPeriod::create([
        'name' => 'Période fermée',
        'starts_on' => today()->startOfMonth(),
        'ends_on' => today()->endOfMonth(),
        'status' => PeriodStatus::Closed,
        'created_by' => $this->user->id,
        'closed_at' => now(),
        'closed_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->patch(route('accounting.entries.post', $entry))
        ->assertConflict();

    expect($entry->refresh()->status)->toBe(EntryStatus::Draft);
});

it('enforces manual entry permissions', function () {
    $commercial = User::factory()->create();
    $commercial->assignRole(Role::Commercial->value);

    $this->actingAs($commercial)->get(route('accounting.entries.create'))->assertForbidden();
    $this->actingAs($commercial)->post(route('accounting.entries.store'), $this->payload)->assertForbidden();
});
