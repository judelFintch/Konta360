<?php

use App\Mail\AuthenticationCodeMail;
use App\Models\Company;
use App\Models\Plan;
use App\Models\PlatformEvent;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\Billing\Enums\SubscriptionPaymentStatus;
use App\Modules\Billing\Enums\SubscriptionStatus;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;

beforeEach(function () {
    Date::setTestNow('2026-10-08 10:00:00');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->operator = User::factory()->platformAdmin()->create(['name' => 'Opérateur']);

    $this->trial = Company::factory()->create(['name' => 'Alpha Évaluation', 'trial_ends_at' => '2026-10-12']);
    $this->paid = Company::factory()->onPlan('pro')->create(['name' => 'Bêta Payante', 'subscription_ends_at' => '2026-12-31']);
    $this->expired = Company::factory()->expired()->create(['name' => 'Gamma Expirée']);
    $this->admin = User::factory()->forCompany($this->paid)->create(['email' => 'admin@beta.test', 'last_login_at' => '2026-10-07 09:00:00']);
    $this->admin->assignRole(Role::Administrateur->value);
});

it('gives an overview of the subscribers', function () {
    $this->actingAs($this->operator)->get(route('platform.dashboard'))
        ->assertOk()
        ->assertSee('Tableau de bord')
        ->assertSee('35,00 USD')         // Recurring revenue: Bêta on Pro.
        ->assertSee('Alpha Évaluation');  // Its evaluation ends within a week.
});

it('sends platform administrators to the dashboard after signing in', function () {
    $this->actingAs($this->operator)->get(route('dashboard'))->assertRedirect(route('platform.dashboard'));
});

it('filters subscribers by subscription status', function (SubscriptionStatus $status, string $shown, array $hidden) {
    $response = $this->actingAs($this->operator)->get(route('platform.companies.index', ['status' => $status->value]))->assertOk()->assertSee($shown);
    foreach ($hidden as $name) {
        $response->assertDontSee($name);
    }
})->with([
    'évaluation' => [SubscriptionStatus::Trial, 'Alpha Évaluation', ['Bêta Payante', 'Gamma Expirée']],
    'payant' => [SubscriptionStatus::Active, 'Bêta Payante', ['Alpha Évaluation', 'Gamma Expirée']],
    'expiré' => [SubscriptionStatus::Expired, 'Gamma Expirée', ['Alpha Évaluation', 'Bêta Payante']],
]);

it('finds a subscriber by an administrator’s email', function () {
    $this->actingAs($this->operator)->get(route('platform.companies.index', ['search' => 'admin@beta']))
        ->assertSee('Bêta Payante')
        ->assertDontSee('Alpha Évaluation');
});

it('exports the subscribers as CSV', function () {
    $csv = $this->actingAs($this->operator)->get(route('platform.companies.export', ['status' => 'active']))
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain('Bêta Payante')->toContain('admin@beta.test')->not->toContain('Gamma Expirée');
});

it('shows a subscriber’s file without its accounting data', function () {
    $this->actingAs($this->operator)->get(route('platform.companies.show', $this->paid))
        ->assertOk()
        ->assertSee('Bêta Payante')
        ->assertSee('admin@beta.test')
        ->assertSee('07/10/2026 09:00')
        ->assertSee('Notes internes');
});

it('changes the plan of a subscriber and logs it', function () {
    $this->actingAs($this->operator)->patch(route('platform.companies.plan', $this->trial), [
        'plan_id' => Plan::where('code', 'entreprise')->value('id'),
    ])->assertSessionHasNoErrors();

    expect($this->trial->refresh()->plan->code)->toBe('entreprise')
        ->and(PlatformEvent::where('company_id', $this->trial->id)->value('description'))->toContain('Entreprise');
});

it('extends an evaluation, restarting it today when it had expired', function () {
    $this->actingAs($this->operator)->patch(route('platform.companies.trial', $this->trial), ['days' => 10]);
    expect($this->trial->refresh()->trial_ends_at->toDateString())->toBe('2026-10-22');

    $this->actingAs($this->operator)->patch(route('platform.companies.trial', $this->expired), ['days' => 10]);
    expect($this->expired->refresh()->trial_ends_at->toDateString())->toBe('2026-10-17')
        ->and($this->expired->subscriptionStatus())->toBe(SubscriptionStatus::Trial);
});

it('records a payment received directly, confirmed at once', function () {
    $this->actingAs($this->operator)->post(route('platform.companies.payments.store', $this->expired), [
        'plan_id' => Plan::where('code', 'essentiel')->value('id'), 'months' => 3, 'method' => 'cash', 'reference' => 'RECU-042',
    ])->assertSessionHasNoErrors();

    $payment = SubscriptionPayment::query()->withoutGlobalScopes()->where('company_id', $this->expired->id)->firstOrFail();
    expect($payment->status)->toBe(SubscriptionPaymentStatus::Confirmed)
        ->and($payment->amount)->toBe('45.00')
        ->and($payment->reviewed_by)->toBe($this->operator->id)
        ->and($this->expired->refresh()->subscription_ends_at->toDateString())->toBe('2027-01-07')
        ->and($this->expired->subscriptionStatus())->toBe(SubscriptionStatus::Active)
        ->and(PlatformEvent::where('action', 'payment_recorded')->exists())->toBeTrue();
});

it('deactivates a subscriber’s user, only within that company', function () {
    $this->actingAs($this->operator)->patch(route('platform.companies.users.toggle', [$this->paid, $this->admin]))->assertRedirect();
    expect($this->admin->refresh()->is_active)->toBeFalse();

    $this->actingAs($this->operator)->patch(route('platform.companies.users.toggle', [$this->trial, $this->admin]))->assertNotFound();
});

it('keeps internal notes', function () {
    $this->actingAs($this->operator)->put(route('platform.companies.notes', $this->paid), ['platform_notes' => 'Remise accordée en octobre.']);

    expect($this->paid->refresh()->platform_notes)->toBe('Remise accordée en octobre.');
});

it('logs suspensions with their author', function () {
    $this->actingAs($this->operator)->patch(route('platform.companies.suspend', $this->paid));

    $event = PlatformEvent::where('company_id', $this->paid->id)->firstOrFail();
    expect($event->action)->toBe('suspension')->and($event->actor_id)->toBe($this->operator->id);
    $this->actingAs($this->operator)->get(route('platform.companies.show', $this->paid))->assertSee('Accès suspendu');
});

it('keeps every super admin page and action away from company users', function (string $method, string $route) {
    $parameters = str_contains($route, 'toggle') ? [$this->paid, $this->admin] : (str_contains($route, 'companies.') && ! str_ends_with($route, 'index') && ! str_ends_with($route, 'export') ? [$this->paid] : []);

    $this->actingAs($this->admin)->call($method, route($route, $parameters))->assertForbidden();
})->with([
    ['GET', 'platform.dashboard'],
    ['GET', 'platform.companies.index'],
    ['GET', 'platform.companies.export'],
    ['GET', 'platform.companies.show'],
    ['PUT', 'platform.companies.notes'],
    ['PATCH', 'platform.companies.plan'],
    ['PATCH', 'platform.companies.trial'],
    ['POST', 'platform.companies.payments.store'],
    ['PATCH', 'platform.companies.users.toggle'],
]);

it('records the last sign-in of a user', function () {
    $user = User::factory()->forCompany($this->paid)->create();
    Mail::fake();
    Volt::test('pages.auth.login')->set('form.email', $user->email)->set('form.password', 'password')->call('login');
    $code = Mail::sent(AuthenticationCodeMail::class)->last()->code;

    Volt::test('pages.auth.login-code')->set('code', $code)->call('verify');

    expect($user->refresh()->last_login_at?->toDateTimeString())->toBe('2026-10-08 10:00:00');
});
