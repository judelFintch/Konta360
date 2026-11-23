<?php

use App\Livewire\Actions\Logout;
use App\Mail\AuthenticationCodeMail;
use App\Models\Company;
use App\Models\LoginEvent;
use App\Models\User;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\Authentication\Enums\LoginEventType;
use App\Modules\Authentication\Services\TrustedDevices;
use App\Modules\Companies\Services\CompanyDataExporter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Livewire\Volt\Volt;

beforeEach(function () {
    Mail::fake();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->company = Company::query()->oldest('id')->first();
    $this->admin = User::factory()->forCompany($this->company)->create(['name' => 'Awa Admin']);
    $this->admin->assignRole(Role::Administrateur->value);
    $this->accountant = User::factory()->forCompany($this->company)->create(['name' => 'Bruno Compta', 'last_login_at' => now()->subDays(3)]);
    $this->accountant->assignRole(Role::Comptable->value);
    $this->newcomer = User::factory()->forCompany($this->company)->create(['name' => 'Chloé Nouvelle']);
    $this->newcomer->assignRole(Role::Commercial->value);
});

function signIn(User $user, ?string $code = null, bool $trust = false): void
{
    Volt::test('pages.auth.login')->set('form.email', $user->email)->set('form.password', 'password')->call('login');
    $code ??= Mail::sent(AuthenticationCodeMail::class, fn ($mail) => $mail->hasTo($user->email))->last()->code;
    Volt::test('pages.auth.login-code')->set('code', $code)->set('trustDevice', $trust)->call('verify');
}

/** A database session last active $minutesAgo minutes ago. */
function activeSession(User $user, int $minutesAgo): void
{
    config(['session.driver' => 'database']);
    DB::table('sessions')->insert([
        'id' => Str::random(40), 'user_id' => $user->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test',
        'payload' => '', 'last_activity' => now()->subMinutes($minutesAgo)->getTimestamp(),
    ]);
}

it('records a sign-in with the code, its company, address and browser', function () {
    signIn($this->accountant);

    $event = LoginEvent::sole();
    expect($event->event)->toBe(LoginEventType::SignedIn)
        ->and($event->method)->toBe('code')
        ->and($event->user_id)->toBe($this->accountant->id)
        ->and($event->company_id)->toBe($this->company->id)
        ->and($event->ip_address)->toBe('127.0.0.1');
});

it('records a sign-in on a trusted device', function () {
    signIn($this->accountant, trust: true);
    $cookie = Cookie::queued(TrustedDevices::COOKIE)->getValue();
    Auth::logout();

    Livewire::withCookie(TrustedDevices::COOKIE, $cookie)->test('pages.auth.login')
        ->set('form.email', $this->accountant->email)->set('form.password', 'password')->call('login');

    expect(LoginEvent::latest('id')->first()->method)->toBe('trusted_device');
});

it('records wrong passwords and refused codes on existing accounts only', function () {
    Volt::test('pages.auth.login')->set('form.email', $this->accountant->email)->set('form.password', 'mauvais')->call('login');
    Volt::test('pages.auth.login')->set('form.email', 'inconnu@nulle-part.test')->set('form.password', 'mauvais')->call('login');
    signIn($this->accountant, code: '00000000');

    expect(LoginEvent::orderBy('id')->pluck('event')->all())->toBe([LoginEventType::WrongPassword, LoginEventType::WrongCode])
        ->and(LoginEvent::pluck('user_id')->unique()->all())->toBe([$this->accountant->id]);
});

it('records sign-outs', function () {
    $this->actingAs($this->accountant);
    app(Logout::class)();

    expect(LoginEvent::sole()->event)->toBe(LoginEventType::SignedOut);
});

it('shows administrators who is online now and when the others were last seen', function () {
    activeSession($this->accountant, 2);
    activeSession($this->admin, 0);

    $this->actingAs($this->admin)->get(route('administration.users.index'))
        ->assertOk()
        ->assertSee('Connectés maintenant')
        ->assertSee('Awa Admin et Bruno Compta')
        ->assertSee('En ligne')
        ->assertSee('Jamais connecté');

    DB::table('sessions')->where('user_id', $this->accountant->id)->update(['last_activity' => now()->subHour()->getTimestamp()]);
    $this->get(route('administration.users.index'))->assertSee('Vu il y a 1 heure')->assertDontSee('Awa Admin et Bruno Compta');
});

it('shows the team presence on the administrators’ dashboard only', function () {
    activeSession($this->accountant, 1);

    $this->actingAs($this->admin)->get(route('dashboard'))
        ->assertSee('Équipe')
        ->assertSee('1 connecté(s) maintenant sur 3 utilisateur(s) actif(s).');

    $this->actingAs($this->accountant)->get(route('dashboard'))->assertDontSee('connecté(s) maintenant');
});

it('lists the company’s sign-in history, filtered, to administrators only', function () {
    signIn($this->accountant);
    Volt::test('pages.auth.login')->set('form.email', $this->newcomer->email)->set('form.password', 'mauvais')->call('login');
    $other = User::factory()->forCompany(Company::factory()->create())->create(['name' => 'Zoé Ailleurs']);
    Volt::test('pages.auth.login')->set('form.email', $other->email)->set('form.password', 'mauvais')->call('login');
    Auth::logout();

    $this->actingAs($this->admin)->get(route('administration.logins.index'))
        ->assertOk()
        ->assertSee('Bruno Compta')
        ->assertSee('Chloé Nouvelle')
        ->assertSee('Mot de passe erroné')
        ->assertSee('par code par e-mail')
        ->assertDontSee('Zoé Ailleurs');

    // Bruno stays in the user filter list, but his sign-in is filtered out.
    $this->get(route('administration.logins.index', ['user_id' => $this->newcomer->id]))
        ->assertSee('Mot de passe erroné')->assertDontSee('par code par e-mail');
    $this->get(route('administration.logins.index', ['user_id' => $other->id]))
        ->assertSee('Aucune connexion enregistrée.');

    $this->actingAs($this->accountant)->get(route('administration.logins.index'))->assertForbidden();
});

it('shows the recent sign-ins on the user’s administration page', function () {
    signIn($this->accountant);
    Auth::logout();

    $this->actingAs($this->admin)->get(route('administration.users.edit', $this->accountant))
        ->assertOk()
        ->assertSee('Connexions récentes')
        ->assertSee('Connexion');
});

it('shows each user their own sign-ins on their profile', function () {
    signIn($this->accountant);
    Volt::test('pages.auth.login')->set('form.email', $this->newcomer->email)->set('form.password', 'mauvais')->call('login');

    $this->actingAs($this->newcomer);
    Volt::test('profile.login-history')
        ->assertSee('Mes dernières connexions')
        ->assertSee('Mot de passe erroné')
        ->assertDontSee('par code par e-mail');
});

it('exports the sign-in history with the company data', function () {
    signIn($this->accountant);

    expect(CompanyDataExporter::TABLES)->toContain('login_events');
});
