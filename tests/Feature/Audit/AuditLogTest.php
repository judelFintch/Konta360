<?php

use App\Models\AuditLog;
use App\Models\Party;
use App\Models\User;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\Parties\Enums\PartyType;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::Administrateur->value);
});

it('records successful mutating operations without form contents', function () {
    $this->actingAs($this->admin)->post(route('parties.store'), [
        'type' => PartyType::Supplier->value,
        'name' => 'Fournisseur audité',
        'email' => 'secret@example.test',
        'is_active' => 1,
    ])->assertRedirect();

    $log = AuditLog::firstOrFail();

    expect($log->user_id)->toBe($this->admin->id)
        ->and($log->action)->toBe('creation')
        ->and($log->route_name)->toBe('parties.store')
        ->and($log->http_method)->toBe('POST')
        ->and(json_encode($log->metadata))->not->toContain('secret@example.test');
});

it('records the model affected by an update', function () {
    $party = Party::create(['type' => PartyType::Supplier, 'name' => 'Avant', 'is_active' => true]);

    $this->actingAs($this->admin)->put(route('parties.update', $party), [
        'type' => PartyType::Supplier->value,
        'name' => 'Après',
        'is_active' => 1,
    ])->assertRedirect();

    $log = AuditLog::firstOrFail();

    expect($log->action)->toBe('modification')
        ->and($log->subject_type)->toBe(Party::class)
        ->and($log->subject_id)->toBe($party->id);
});

it('does not record rejected operations', function () {
    $this->actingAs($this->admin)->post(route('parties.store'), [
        'type' => PartyType::Supplier->value,
        'name' => '',
        'is_active' => 1,
    ])->assertSessionHasErrors('name');

    expect(AuditLog::count())->toBe(0);
});

it('allows authorized roles to filter and inspect audit logs', function () {
    $log = AuditLog::create([
        'user_id' => $this->admin->id,
        'action' => 'validation',
        'route_name' => 'invoices.validate',
        'http_method' => 'PATCH',
        'description' => 'Validation d’une facture',
        'ip_address' => '127.0.0.1',
    ]);

    $this->actingAs($this->admin)
        ->get(route('audit-logs.index', ['action' => 'validation']))
        ->assertOk()
        ->assertSee('Validation d’une facture');
    $this->actingAs($this->admin)->get(route('audit-logs.show', $log))->assertOk();
});

it('forbids users without audit permission', function () {
    $commercial = User::factory()->create();
    $commercial->assignRole(Role::Commercial->value);

    $this->actingAs($commercial)->get(route('audit-logs.index'))->assertForbidden();
});
