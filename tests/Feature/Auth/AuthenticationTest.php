<?php

use App\Mail\AuthenticationCodeMail;
use App\Models\AuthenticationCode;
use App\Models\Company;
use App\Models\User;
use App\Modules\Authentication\Enums\AuthenticationCodePurpose;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;

beforeEach(fn () => Mail::fake());

/**
 * The code in the last email sent to the user.
 */
function lastCodeSentTo(User $user, AuthenticationCodePurpose $purpose = AuthenticationCodePurpose::Login): string
{
    $mail = Mail::sent(AuthenticationCodeMail::class, fn ($mail) => $mail->hasTo($user->email) && $mail->purpose === $purpose)->last();
    expect($mail)->not->toBeNull();

    return $mail->code;
}

function submitPassword(User $user, string $password = 'password', bool $remember = false)
{
    return Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', $password)
        ->set('form.remember', $remember)
        ->call('login');
}

test('login screen can be rendered', function () {
    $this->get('/login')->assertOk()->assertSeeVolt('pages.auth.login');
});

test('the password alone does not sign in: it sends an eight-digit code by email', function () {
    $user = User::factory()->create();

    submitPassword($user)->assertHasNoErrors()->assertRedirect(route('login.code'));

    $this->assertGuest();
    $code = lastCodeSentTo($user);
    expect($code)->toMatch('/^\d{8}$/');

    $mail = Mail::sent(AuthenticationCodeMail::class)->last();
    expect($mail->envelope()->subject)->toContain('Votre code de connexion');
    $mail->assertSeeInHtml(implode(' ', str_split($code, 4)));
});

test('users sign in with the code sent by email', function () {
    $user = User::factory()->create();
    submitPassword($user);

    Volt::test('pages.auth.login-code')
        ->set('code', lastCodeSentTo($user))
        ->call('verify')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('the code page needs a password checked first', function () {
    $this->get(route('login.code'))->assertRedirect(route('login'));
});

test('a wrong code does not sign in, and five wrong codes burn it', function () {
    $user = User::factory()->create();
    submitPassword($user);
    $good = lastCodeSentTo($user);
    $wrong = str_pad((string) (((int) $good + 1) % 100000000), 8, '0', STR_PAD_LEFT);

    $page = Volt::test('pages.auth.login-code');
    foreach (range(1, 5) as $attempt) {
        $page->set('code', $wrong)->call('verify')->assertHasErrors('code');
    }
    $page->set('code', $good)->call('verify')->assertHasErrors('code');

    $this->assertGuest();
});

test('an expired code does not sign in: the user starts again from the password', function () {
    $user = User::factory()->create();
    submitPassword($user);

    $this->travel(11)->minutes();

    $this->get(route('login.code'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('an expired code is refused even if the page stayed open', function () {
    $user = User::factory()->create();
    submitPassword($user);
    $code = lastCodeSentTo($user);
    $page = Volt::test('pages.auth.login-code');

    AuthenticationCode::query()->update(['expires_at' => now()->subSecond()]);

    $page->set('code', $code)->call('verify')->assertHasErrors('code');
    $this->assertGuest();
});

test('only the latest code works, and a new one cannot be requested within a minute', function () {
    $user = User::factory()->create();
    submitPassword($user);
    $first = lastCodeSentTo($user);

    $page = Volt::test('pages.auth.login-code');
    $page->call('resend')->assertHasErrors('code');

    $this->travel(61)->seconds();
    $page->call('resend')->assertHasNoErrors();
    $second = lastCodeSentTo($user);

    if ($first !== $second) {
        $page->set('code', $first)->call('verify')->assertHasErrors('code');
    }
    $page->set('code', $second)->call('verify')->assertHasNoErrors();
    $this->assertAuthenticatedAs($user);
});

test('codes can be typed with spaces, as printed in the email', function () {
    $user = User::factory()->create();
    submitPassword($user);

    Volt::test('pages.auth.login-code')
        ->set('code', implode(' ', str_split(lastCodeSentTo($user), 4)))
        ->call('verify')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($user);
});

test('users can not authenticate with invalid password, and no code is sent', function () {
    $user = User::factory()->create();

    submitPassword($user, 'wrong-password')->assertHasErrors()->assertNoRedirect();

    $this->assertGuest();
    Mail::assertNothingSent();
});

test('a deactivated user gets no code', function () {
    $user = User::factory()->create(['is_active' => false]);

    submitPassword($user)->assertHasErrors('form.email');

    Mail::assertNothingSent();
});

test('a company suspended while the code was pending blocks the sign-in', function () {
    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create();
    submitPassword($user);
    $company->forceFill(['suspended_at' => now()])->save();

    Volt::test('pages.auth.login-code')->set('code', lastCodeSentTo($user))->call('verify')->assertHasErrors('code');

    $this->assertGuest();
});

test('signing in with a code also confirms an unverified address', function () {
    $user = User::factory()->unverified()->create();
    submitPassword($user);

    Volt::test('pages.auth.login-code')->set('code', lastCodeSentTo($user))->call('verify');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('the code is stored hashed, never in clear', function () {
    $user = User::factory()->create();
    submitPassword($user);
    $code = lastCodeSentTo($user);

    $stored = AuthenticationCode::query()->where('user_id', $user->id)->value('code_hash');
    expect($stored)->not->toContain($code)->toHaveLength(64);
});

test('navigation menu can be rendered', function () {
    $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk()->assertSeeVolt('layout.navigation');
});

test('users can logout', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('layout.navigation')->call('logout')->assertHasNoErrors()->assertRedirect('/');

    $this->assertGuest();
});
