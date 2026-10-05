<?php

use App\Mail\AuthenticationCodeMail;
use App\Models\User;
use App\Modules\Authentication\Enums\AuthenticationCodePurpose;
use App\Modules\Authentication\Services\AuthenticationCodeService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;

beforeEach(fn () => Mail::fake());

function verificationCodeFor(User $user): string
{
    return Mail::sent(AuthenticationCodeMail::class, fn ($mail) => $mail->hasTo($user->email)
        && $mail->purpose === AuthenticationCodePurpose::EmailVerification)->last()->code;
}

test('email verification screen can be rendered', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get('/verify-email')->assertOk()->assertSee('Confirmez votre adresse e-mail');
});

test('registering sends an eight-digit confirmation code by email', function () {
    $user = User::factory()->unverified()->create();

    $user->sendEmailVerificationNotification();

    expect(verificationCodeFor($user))->toMatch('/^\d{8}$/');
    expect(Mail::sent(AuthenticationCodeMail::class)->last()->envelope()->subject)->toContain('Confirmez votre adresse e-mail');
});

test('email is verified with the code', function () {
    $user = User::factory()->unverified()->create();
    $user->sendEmailVerificationNotification();
    Event::fake([Verified::class]);

    $this->actingAs($user);
    Volt::test('pages.auth.verify-email')
        ->set('code', verificationCodeFor($user))
        ->call('verify')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('email is not verified with a wrong code', function () {
    $user = User::factory()->unverified()->create();
    $user->sendEmailVerificationNotification();

    $this->actingAs($user);
    Volt::test('pages.auth.verify-email')->set('code', '00000000')->call('verify')->assertHasErrors('code');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('a sign-in code cannot confirm the address, and the other way round', function () {
    $user = User::factory()->unverified()->create();
    app(AuthenticationCodeService::class)->send($user, AuthenticationCodePurpose::Login);
    $loginCode = Mail::sent(AuthenticationCodeMail::class)->last()->code;

    $this->actingAs($user);
    Volt::test('pages.auth.verify-email')->set('code', $loginCode)->call('verify')->assertHasErrors('code');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('unverified users are kept on the confirmation page', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('verification.notice'));
});
