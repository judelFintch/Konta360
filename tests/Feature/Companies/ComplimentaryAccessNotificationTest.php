<?php

use App\Mail\ComplimentaryAccessMail;
use App\Models\Company;
use App\Models\PlatformEvent;
use App\Models\User;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\Billing\Enums\SubscriptionStatus;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->operator = User::factory()->platformAdmin()->create();
    $this->company = Company::factory()->create(['name' => 'École Lumière', 'trial_ends_at' => today()->subDay()]);

    $this->admin = User::factory()->forCompany($this->company)->create(['name' => 'Awa Admin', 'email' => 'awa@lumiere.test']);
    $this->admin->assignRole(Role::Administrateur->value);
    $this->secondAdmin = User::factory()->forCompany($this->company)->create(['email' => 'paul@lumiere.test']);
    $this->secondAdmin->assignRole(Role::Administrateur->value);
    $inactiveAdmin = User::factory()->forCompany($this->company)->create(['email' => 'ancien@lumiere.test', 'is_active' => false]);
    $inactiveAdmin->assignRole(Role::Administrateur->value);
    $accountant = User::factory()->forCompany($this->company)->create(['email' => 'compta@lumiere.test']);
    $accountant->assignRole(Role::Comptable->value);
    // Administrator of another company: never emailed.
    $other = User::factory()->forCompany(Company::factory()->create())->create(['email' => 'autre@ailleurs.test']);
    $other->assignRole(Role::Administrateur->value);

    Mail::fake();
});

it('emails each active administrator when the access is offered', function () {
    $this->actingAs($this->operator)
        ->patch(route('platform.companies.exempt', $this->company), ['notify' => '1'])
        ->assertRedirect()
        ->assertSessionHas('success', fn (string $message) => str_contains($message, 'awa@lumiere.test, paul@lumiere.test'));

    expect($this->company->refresh()->subscriptionStatus())->toBe(SubscriptionStatus::Exempt);
    Mail::assertSentCount(2);
    Mail::assertSent(ComplimentaryAccessMail::class, fn (ComplimentaryAccessMail $mail) => $mail->hasTo('awa@lumiere.test')
        && $mail->granted
        && $mail->envelope()->subject === 'Konta360 vous offre l’accès — École Lumière');
    Mail::assertSent(ComplimentaryAccessMail::class, fn (ComplimentaryAccessMail $mail) => $mail->hasTo('paul@lumiere.test') && count($mail->to) === 1);

    $event = PlatformEvent::where('action', 'exemption_email')->firstOrFail();
    expect($event->company_id)->toBe($this->company->id)
        ->and($event->actor_id)->toBe($this->operator->id)
        ->and($event->details['sent'])->toBe(['awa@lumiere.test', 'paul@lumiere.test']);
});

it('sends nothing when the operator unticks the email', function () {
    $this->actingAs($this->operator)
        ->patch(route('platform.companies.exempt', $this->company), ['notify' => '0'])
        ->assertSessionHas('success', fn (string $message) => str_contains($message, 'Aucun e-mail envoyé'));

    expect($this->company->refresh()->billing_exempt)->toBeTrue();
    Mail::assertNothingSent();
});

it('tells the administrators when the offered access ends', function () {
    $this->company->forceFill(['billing_exempt' => true])->save();

    $this->actingAs($this->operator)->patch(route('platform.companies.exempt', $this->company));

    Mail::assertSent(ComplimentaryAccessMail::class, fn (ComplimentaryAccessMail $mail) => ! $mail->granted
        && $mail->envelope()->subject === 'Fin de l’accès offert à Konta360 — École Lumière');
});

it('writes a clear message for both cases', function () {
    $granted = (new ComplimentaryAccessMail($this->company, $this->admin, true))->render();
    expect($granted)->toContain('Bonjour Awa Admin')
        ->toContain('offre à <strong>École Lumière</strong> l’accès complet')
        ->toContain(route('login'));

    // Expired trial: read-only once the offer ends.
    $ended = (new ComplimentaryAccessMail($this->company, $this->admin, false))->render();
    expect($ended)->toContain('a pris fin')
        ->toContain('restent consultables et exportables')
        ->toContain(route('subscription.show'));

    // Trial still running: tells until when.
    $this->company->forceFill(['trial_ends_at' => today()->addDays(10)])->save();
    expect((new ComplimentaryAccessMail($this->company->refresh(), $this->admin, false))->render())
        ->toContain('jusqu’au <strong>'.today()->addDays(10)->format('d/m/Y').'</strong>');
});

it('resends the email to companies that already have the offered access', function () {
    $this->actingAs($this->operator)->post(route('platform.companies.exempt.notify', $this->company))->assertStatus(409);
    Mail::assertNothingSent();

    $this->company->forceFill(['billing_exempt' => true])->save();
    $this->actingAs($this->operator)->get(route('platform.companies.show', $this->company))->assertSee('Renvoyer l’e-mail');

    $this->post(route('platform.companies.exempt.notify', $this->company))->assertSessionHas('success');
    Mail::assertSent(ComplimentaryAccessMail::class, 2);
});

it('keeps the offered access and warns the operator when the email cannot be sent', function () {
    Mail::shouldReceive('to')->andThrow(new TransportException('Connection refused'));

    $this->actingAs($this->operator)
        ->patch(route('platform.companies.exempt', $this->company))
        ->assertSessionHas('warning', fn (string $message) => str_contains($message, 'n’a pas pu être envoyé à awa@lumiere.test, paul@lumiere.test'));

    expect($this->company->refresh()->billing_exempt)->toBeTrue()
        ->and(PlatformEvent::where('action', 'exemption_email')->first()->details['failed'])->toHaveCount(2);
});

it('warns the operator when the company has no active administrator', function () {
    $this->admin->forceFill(['is_active' => false])->save();
    $this->secondAdmin->forceFill(['is_active' => false])->save();

    $this->actingAs($this->operator)
        ->patch(route('platform.companies.exempt', $this->company))
        ->assertSessionHas('warning', fn (string $message) => str_contains($message, 'Aucun administrateur actif'));
    Mail::assertNothingSent();
});
