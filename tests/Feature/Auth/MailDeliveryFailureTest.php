<?php

use App\Models\AuthenticationCode;
use App\Models\User;
use App\Modules\Authentication\Enums\AuthenticationCodePurpose;
use App\Modules\Authentication\Services\AuthenticationCodeService;
use App\Modules\Authentication\Services\PendingLogin;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(function () {
    Mail::shouldReceive('to')->andReturnSelf();
    Mail::shouldReceive('send')->andThrow(new TransportException('550 L-RF3 Invalid recipient domain'));
});

test('smtp rejection appears in the login form without starting a pending login', function () {
    $user = User::factory()->create();

    Volt::test('pages.auth.login')->set('form.email', $user->email)->set('form.password', 'password')
        ->call('login')->assertHasErrors('mail')->assertNoRedirect()
        ->assertSee('Votre code n’a pas pu être envoyé')->assertDontSee('L-RF3');

    $this->assertGuest();
    expect(PendingLogin::user())->toBeNull();
    expect(AuthenticationCode::count())->toBe(0);
    expect(app(AuthenticationCodeService::class)->secondsBeforeResend($user, AuthenticationCodePurpose::Login))->toBe(0);
});

test('failed resend is displayed on the verification page', function () {
    $this->actingAs(User::factory()->unverified()->create());
    Volt::test('pages.auth.verify-email')->call('resend')->assertHasErrors('mail')
        ->assertSee('Votre code n’a pas pu être envoyé');
    expect(session('status'))->toBeNull();
});

test('registration remains recoverable when the confirmation email fails', function () {
    Volt::test('pages.auth.register')->set('company_name', 'Mail Test')->set('name', 'Test User')
        ->set('email', 'mail-test@example.com')->set('password', 'password')
        ->set('password_confirmation', 'password')->set('terms', true)->call('register')
        ->assertRedirect(route('verification.notice'));

    $this->assertAuthenticated();
    expect(auth()->user()->hasVerifiedEmail())->toBeFalse();
    $this->get(route('verification.notice'))->assertOk()->assertSee('Votre code n’a pas pu être envoyé');
    $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
});

test('a failed replacement keeps the previous code usable', function () {
    $user = User::factory()->create();
    $record = new AuthenticationCode;
    $record->forceFill([
        'user_id' => $user->id,
        'purpose' => AuthenticationCodePurpose::Login,
        'code_hash' => hash_hmac('sha256', $user->id.'|'.AuthenticationCodePurpose::Login->value.'|12345678', config('app.key')),
        'expires_at' => now()->addMinutes(5),
        'created_at' => now()->subMinutes(2),
    ])->save();
    $service = app(AuthenticationCodeService::class);

    expect(fn () => $service->send($user, AuthenticationCodePurpose::Login))
        ->toThrow(ValidationException::class);
    $service->verify($user, AuthenticationCodePurpose::Login, '12345678');
    expect($record->fresh()->consumed_at)->not->toBeNull();
});
