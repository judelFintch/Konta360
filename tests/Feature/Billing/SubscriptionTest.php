<?php

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\Billing\Enums\SubscriptionPaymentStatus;
use App\Modules\Billing\Enums\SubscriptionStatus;
use Illuminate\Support\Facades\Date;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->operator = User::factory()->platformAdmin()->create();
    $this->company = Company::factory()->onPlan('essentiel')->create();
    $this->admin = User::factory()->forCompany($this->company)->create();
    $this->admin->assignRole(Role::Administrateur->value);
});

function declarePayment(User $admin, string $plan = 'pro', int $months = 3, string $reference = 'MP-123456'): SubscriptionPayment
{
    test()->actingAs($admin)->post(route('subscription.payments.store'), [
        'plan_id' => Plan::where('code', $plan)->value('id'),
        'months' => $months,
        'method' => 'mobile_money',
        'reference' => $reference,
        // Ignored: the amount always comes from the plan.
        'amount' => 1,
    ])->assertSessionHasNoErrors();

    return SubscriptionPayment::query()->withoutGlobalScopes()->latest('id')->firstOrFail();
}

it('keeps the companies that existed before subscriptions on complimentary access', function () {
    $legacy = Company::query()->oldest('id')->first();

    expect($legacy->billing_exempt)->toBeTrue()
        ->and($legacy->subscriptionStatus())->toBe(SubscriptionStatus::Exempt);
});

it('works out the subscription status from the trial and paid dates', function () {
    expect($this->company->subscriptionStatus())->toBe(SubscriptionStatus::Trial);

    $this->company->trial_ends_at = today();
    expect($this->company->subscriptionStatus())->toBe(SubscriptionStatus::Trial);

    $this->company->trial_ends_at = today()->subDay();
    expect($this->company->subscriptionStatus())->toBe(SubscriptionStatus::Expired);

    $this->company->subscription_ends_at = today();
    expect($this->company->subscriptionStatus())->toBe(SubscriptionStatus::Active);

    $this->company->billing_exempt = true;
    expect($this->company->subscriptionStatus())->toBe(SubscriptionStatus::Exempt);
});

it('records a declared payment with the amount computed from the plan', function () {
    $payment = declarePayment($this->admin, 'pro', 3);

    expect($payment->company_id)->toBe($this->company->id)
        ->and($payment->status)->toBe(SubscriptionPaymentStatus::Pending)
        ->and($payment->amount)->toBe('105.00')
        ->and($payment->currency)->toBe('USD')
        ->and($this->company->refresh()->subscription_ends_at)->toBeNull();

    $this->actingAs($this->admin)->get(route('subscription.show'))->assertOk()->assertSee('MP-123456');
});

it('only lets administrators declare payments', function () {
    $accountant = User::factory()->forCompany($this->company)->create();
    $accountant->assignRole(Role::Comptable->value);

    $this->actingAs($accountant)->get(route('subscription.show'))->assertOk()->assertDontSee('Déclarer le paiement');
    $this->actingAs($accountant)->post(route('subscription.payments.store'), [
        'plan_id' => Plan::where('code', 'pro')->value('id'), 'months' => 1, 'method' => 'cash', 'reference' => 'X',
    ])->assertForbidden();
});

it('extends the subscription after the remaining trial once the platform confirms the payment', function () {
    Date::setTestNow('2026-10-06');
    $this->company->forceFill(['trial_ends_at' => '2026-10-20'])->save();
    $payment = declarePayment($this->admin, 'pro', 3);

    $this->actingAs($this->operator)->patch(route('platform.payments.confirm', $payment->id))->assertRedirect();

    $payment->refresh();
    $company = $this->company->refresh();
    expect($payment->status)->toBe(SubscriptionPaymentStatus::Confirmed)
        ->and($payment->reviewed_by)->toBe($this->operator->id)
        ->and($payment->period_starts_on->toDateString())->toBe('2026-10-21')
        ->and($payment->period_ends_on->toDateString())->toBe('2027-01-20')
        ->and($company->subscription_ends_at->toDateString())->toBe('2027-01-20')
        ->and($company->plan->code)->toBe('pro')
        ->and($company->subscriptionStatus())->toBe(SubscriptionStatus::Active);

    // A second payment follows the first period.
    $next = declarePayment($this->admin, 'pro', 1, 'MP-2');
    $this->actingAs($this->operator)->patch(route('platform.payments.confirm', $next->id));
    expect($this->company->refresh()->subscription_ends_at->toDateString())->toBe('2027-02-20');
});

it('starts a paid period today when the company had already expired', function () {
    Date::setTestNow('2026-10-06');
    $this->company->forceFill(['trial_ends_at' => '2026-09-01'])->save();
    $payment = declarePayment($this->admin, 'essentiel', 1);

    $this->actingAs($this->operator)->patch(route('platform.payments.confirm', $payment->id));

    expect($payment->refresh()->period_starts_on->toDateString())->toBe('2026-10-06')
        ->and($this->company->refresh()->subscription_ends_at->toDateString())->toBe('2026-11-05');
});

it('rejects a payment with a reason, without extending anything', function () {
    $payment = declarePayment($this->admin);

    $this->actingAs($this->operator)->patch(route('platform.payments.reject', $payment->id), ['rejection_reason' => 'Référence introuvable'])
        ->assertRedirect();

    expect($payment->refresh()->status)->toBe(SubscriptionPaymentStatus::Rejected)
        ->and($payment->rejection_reason)->toBe('Référence introuvable')
        ->and($this->company->refresh()->subscription_ends_at)->toBeNull();
});

it('processes a payment only once', function () {
    $payment = declarePayment($this->admin, 'pro', 1);
    $this->actingAs($this->operator)->patch(route('platform.payments.confirm', $payment->id));
    $endsOn = $this->company->refresh()->subscription_ends_at;

    $this->actingAs($this->operator)->patch(route('platform.payments.confirm', $payment->id))->assertSessionHasErrors('payment');

    expect($this->company->refresh()->subscription_ends_at->toDateString())->toBe($endsOn->toDateString());
});

it('keeps payment review and plans to platform administrators', function () {
    $payment = declarePayment($this->admin);

    $this->actingAs($this->admin)->get(route('platform.payments.index'))->assertForbidden();
    $this->actingAs($this->admin)->patch(route('platform.payments.confirm', $payment->id))->assertForbidden();
    $this->actingAs($this->admin)->get(route('platform.plans.index'))->assertForbidden();

    $this->actingAs($this->operator)->get(route('platform.payments.index'))->assertOk()->assertSee('MP-123456');
});

it('lets the platform change plan prices and limits', function () {
    $plan = Plan::where('code', 'essentiel')->first();
    $this->actingAs($this->operator)->get(route('platform.plans.index'))->assertOk()->assertSee('Entreprise');

    $this->actingAs($this->operator)->put(route('platform.plans.update', $plan), [
        'name' => 'Essentiel', 'monthly_price' => 20, 'currency' => 'USD', 'max_users' => 3,
        'max_invoices_per_month' => '', 'is_active' => 1,
    ])->assertSessionHasNoErrors();

    expect($plan->refresh()->monthly_price)->toBe('20.00')
        ->and($plan->max_users)->toBe(3)
        ->and($plan->max_invoices_per_month)->toBeNull();
});

it('puts an expired company in read-only mode', function () {
    $this->company->forceFill(['trial_ends_at' => today()->subDay()])->save();
    $this->actingAs($this->admin);

    $this->get(route('parties.index'))->assertOk()->assertSee('lecture seule');
    $this->get(route('administration.data.export'))->assertOk();

    $this->post(route('parties.store'), ['type' => 'customer', 'name' => 'Nouveau', 'is_active' => 1])
        ->assertRedirect(route('subscription.show'))
        ->assertSessionHas('subscription_blocked');
    $this->asCompany($this->company, fn () => expect(Party::count())->toBe(0));

    // Renewing stays possible.
    declarePayment($this->admin);
});

it('limits active users to the plan', function () {
    // Essentiel: 2 users. The administrator is the first one.
    User::factory()->forCompany($this->company)->create();
    $this->actingAs($this->admin);

    $this->post(route('administration.users.store'), [
        'name' => 'Troisième', 'email' => 'trois@beta.test', 'password' => 'password',
        'password_confirmation' => 'password', 'role' => Role::Comptable->value,
    ])->assertSessionHasErrors('email');

    expect(User::where('email', 'trois@beta.test')->exists())->toBeFalse();

    $this->company->forceFill(['billing_exempt' => true])->save();
    $this->actingAs($this->admin->fresh())->post(route('administration.users.store'), [
        'name' => 'Troisième', 'email' => 'trois@beta.test', 'password' => 'password',
        'password_confirmation' => 'password', 'role' => Role::Comptable->value,
    ])->assertSessionHasNoErrors();
});

it('limits the invoices validated each month to the plan', function () {
    Plan::where('code', 'essentiel')->update(['max_invoices_per_month' => 1]);
    [$first, $second] = $this->asCompany($this->company, function () {
        $party = Party::forceCreate(['type' => 'customer', 'name' => 'Client', 'is_active' => true]);

        return collect([1, 2])->map(function () use ($party) {
            $invoice = Invoice::forceCreate([
                'party_id' => $party->id, 'status' => 'draft', 'issue_date' => today(), 'due_date' => today(),
                'currency' => 'USD', 'subtotal' => 10, 'discount_total' => 0, 'tax_total' => 0, 'total' => 10, 'created_by' => $this->admin->id,
            ]);
            $invoice->lines()->forceCreate([
                'position' => 1, 'sku' => 'S', 'description' => 'Service', 'unit' => 'unit', 'quantity' => 1, 'unit_price' => 10,
                'discount_rate' => 0, 'tax_rate' => 0, 'subtotal' => 10, 'discount_amount' => 0, 'tax_amount' => 0, 'total' => 10,
            ]);

            return $invoice;
        })->all();
    });
    $this->actingAs($this->admin);

    $this->patch(route('invoices.validate', $first))->assertSessionHas('success');
    $this->patch(route('invoices.validate', $second))->assertSessionHas('error');

    $this->asCompany($this->company, fn () => expect($second->refresh()->number)->toBeNull());
});

it('warns about the end of the trial', function () {
    $this->company->forceFill(['trial_ends_at' => today()->addDays(2)])->save();

    $this->actingAs($this->admin)->get(route('dashboard'))->assertSee('Votre essai gratuit se termine dans 3 jour(s).');
});

it('lets the platform grant complimentary access', function () {
    $this->company->forceFill(['trial_ends_at' => today()->subDay()])->save();

    $this->actingAs($this->operator)->patch(route('platform.companies.exempt', $this->company))->assertRedirect();

    expect($this->company->refresh()->subscriptionStatus())->toBe(SubscriptionStatus::Exempt);
    $this->actingAs($this->admin)->post(route('parties.store'), ['type' => 'customer', 'name' => 'Nouveau', 'is_active' => 1])
        ->assertSessionHasNoErrors();
});
