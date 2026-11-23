<?php

use App\Mail\AuthenticationCodeMail;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Modules\Authentication\Services\TrustedDevices;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Livewire\Volt\Volt;

beforeEach(fn () => Mail::fake());

/**
 * Signs in with the password and the emailed code, then signs out, and
 * returns the trusted-device cookie the browser would keep (or null).
 */
function signInWithCode(User $user, bool $trust = true): ?string
{
    Volt::test('pages.auth.login')->set('form.email', $user->email)->set('form.password', 'password')->call('login');
    $code = Mail::sent(AuthenticationCodeMail::class, fn ($mail) => $mail->hasTo($user->email))->last()->code;

    Volt::test('pages.auth.login-code')->set('code', $code)->set('trustDevice', $trust)->call('verify')->assertHasNoErrors();
    expect(Auth::id())->toBe($user->id);

    $cookie = Cookie::queued(TrustedDevices::COOKIE);
    Auth::logout();
    Cookie::flushQueuedCookies();

    return $cookie && ! $cookie->isCleared() ? $cookie->getValue() : null;
}

function passwordStep(User $user, ?string $cookie, string $password = 'password')
{
    $livewire = $cookie ? Livewire::withCookie(TrustedDevices::COOKIE, $cookie) : Livewire::withCookies([]);

    return $livewire->test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', $password)
        ->call('login');
}

test('a trusted browser signs in with the password alone', function () {
    $user = User::factory()->create();
    $cookie = signInWithCode($user);
    expect($cookie)->not->toBeNull();
    $sent = Mail::sent(AuthenticationCodeMail::class)->count();

    passwordStep($user, $cookie)->assertHasNoErrors()->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    expect(Mail::sent(AuthenticationCodeMail::class)->count())->toBe($sent)
        ->and($user->refresh()->last_login_at)->not->toBeNull();
});

test('only a hash of the browser token is stored, for a limited time', function () {
    $user = User::factory()->create();
    $cookie = signInWithCode($user);
    [, $token] = explode('|', $cookie);

    $device = TrustedDevice::sole();
    expect($device->user_id)->toBe($user->id)
        ->and($device->token_hash)->toBe(hash('sha256', $token))
        ->and($device->token_hash)->not->toContain($token)
        ->and($device->expires_at->toDateString())->toBe(now()->addDays(30)->toDateString());
});

test('the code is still asked when the box is unticked', function () {
    $user = User::factory()->create();
    expect(signInWithCode($user, trust: false))->toBeNull()
        ->and(TrustedDevice::count())->toBe(0);

    passwordStep($user, null)->assertRedirect(route('login.code'));
    $this->assertGuest();
});

test('the password is always required, even on a trusted browser', function () {
    $user = User::factory()->create();
    $cookie = signInWithCode($user);

    passwordStep($user, $cookie, 'wrong-password')->assertHasErrors('form.email');
    $this->assertGuest();
});

test('the code is asked again once the trust has expired', function () {
    $user = User::factory()->create();
    $cookie = signInWithCode($user);

    $this->travel(31)->days();
    passwordStep($user, $cookie)->assertRedirect(route('login.code'));
    $this->assertGuest();
});

test('a browser trusted for one user does not spare the code to another', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $cookie = signInWithCode($alice);

    passwordStep($bob, $cookie)->assertRedirect(route('login.code'));
    passwordStep($bob, str_replace($alice->id.'|', $bob->id.'|', $cookie))->assertRedirect(route('login.code'));
    $this->assertGuest();
});

test('a new password withdraws the trust of every browser', function () {
    $user = User::factory()->create();
    $cookie = signInWithCode($user);

    $user->update(['password' => 'un-nouveau-mot-de-passe']);

    expect(TrustedDevice::count())->toBe(0);
    passwordStep($user, $cookie, 'un-nouveau-mot-de-passe')->assertRedirect(route('login.code'));
});

test('a disabled account stays out, even on a trusted browser', function () {
    $user = User::factory()->create();
    $cookie = signInWithCode($user);
    $user->forceFill(['is_active' => false])->save();

    passwordStep($user, $cookie)->assertHasErrors('form.email');
    $this->assertGuest();
});

test('the feature can be turned off', function () {
    config(['konta360.trusted_device_days' => 0]);
    $user = User::factory()->create();
    Volt::test('pages.auth.login')->set('form.email', $user->email)->set('form.password', 'password')->call('login');

    Volt::test('pages.auth.login-code')->assertDontSee('Ne plus demander de code');
    expect(signInWithCode($user))->toBeNull()
        ->and(TrustedDevice::count())->toBe(0);
});

test('users see and forget their trusted browsers from their profile', function () {
    $user = User::factory()->create();
    signInWithCode($user);
    // A new code can be asked for only after a minute (ADR 0004).
    $this->travel(2)->minutes();
    signInWithCode($user);
    expect($user->trustedDevices()->count())->toBe(2);

    $this->actingAs($user);
    $component = Volt::test('profile.trusted-devices-form')->assertSee('Appareils de confiance')->assertSee('Oublier tous les appareils');
    $component->call('forget', $user->trustedDevices()->first()->id);
    expect($user->trustedDevices()->count())->toBe(1);

    $component->call('forgetAll');
    expect($user->trustedDevices()->count())->toBe(0);
    Volt::test('profile.trusted-devices-form')->assertSee('un code vous est demandé à chaque connexion');
});

test('users cannot forget the browsers of someone else', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    signInWithCode($alice);

    $this->actingAs($bob);
    Volt::test('profile.trusted-devices-form')->call('forget', TrustedDevice::sole()->id);

    expect(TrustedDevice::count())->toBe(1);
});
