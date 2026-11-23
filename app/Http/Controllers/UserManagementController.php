<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\LoginEvent;
use App\Models\User;
use App\Modules\Administration\Enums\Role as RoleEnum;
use App\Modules\Authentication\Enums\LoginEventType;
use App\Modules\Authentication\Services\UserPresence;
use App\Modules\Billing\Services\PlanLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(Request $request, UserPresence $presence): View
    {
        $search = trim((string) $request->query('search'));
        $users = User::query()->whereBelongsTo(Company::current())->with('roles')
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderBy('name')->paginate(20)->withQueryString();

        // Everyone online in the company, not only on this page of the list.
        $companyUsers = User::query()->whereBelongsTo(Company::current())->pluck('name', 'id');
        $online = $presence->online($companyUsers->keys());

        return view('administration.users.index', [
            'users' => $users,
            'search' => $search,
            'lastActivity' => $presence->lastActivity($users->pluck('id')),
            'onlineNames' => $online->keys()->map(fn (int $id) => $companyUsers[$id])->sort()->values(),
            'presenceAvailable' => UserPresence::available(),
            'failedToday' => LoginEvent::query()->forCompany(Company::current())
                ->whereIn('event', [LoginEventType::WrongPassword, LoginEventType::WrongCode])
                ->where('created_at', '>=', today())->count(),
        ]);
    }

    /**
     * Sign-in history of the company's users: who signed in, when, from
     * where, and the failed attempts on their accounts.
     */
    public function logins(Request $request): View
    {
        $company = Company::current();
        $filters = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'event' => ['nullable', Rule::enum(LoginEventType::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $events = LoginEvent::query()->forCompany($company)->with('user:id,name,email')
            ->when($filters['user_id'] ?? null, fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($filters['event'] ?? null, fn ($query, $event) => $query->where('event', $event))
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('created_at')->latest('id')
            ->paginate(30)->withQueryString();

        return view('administration.users.logins', [
            'events' => $events,
            'filters' => $filters,
            'users' => User::query()->whereBelongsTo($company)->orderBy('name')->get(['id', 'name']),
            'types' => LoginEventType::cases(),
        ]);
    }

    public function create(): View
    {
        return view('administration.users.create', ['roles' => RoleEnum::cases()]);
    }

    public function store(Request $request, PlanLimits $limits): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
            'role' => ['required', Rule::enum(RoleEnum::class)],
        ]);
        $limits->ensureCanAddUser(Company::current());
        $user = new User([
            'name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'],
            'is_active' => true,
        ]);
        $user->company()->associate(Company::current());
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole($data['role']);

        return to_route('administration.users.index')->with('success', 'L’utilisateur a été créé.');
    }

    public function edit(User $user): View
    {
        $this->ensureSameCompany($user);

        $presence = app(UserPresence::class);

        return view('administration.users.edit', [
            'managedUser' => $user->load('roles'),
            'roles' => RoleEnum::cases(),
            'lastActivity' => $presence->lastActivity([$user->id])->get($user->id),
            'recentLogins' => LoginEvent::query()->where('user_id', $user->id)->latest('created_at')->latest('id')->limit(10)->get(),
        ]);
    }

    public function update(Request $request, User $user, PlanLimits $limits): RedirectResponse
    {
        $this->ensureSameCompany($user);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'role' => ['required', Rule::enum(RoleEnum::class)],
            'is_active' => ['required', 'boolean'],
            'password' => ['nullable', 'confirmed', 'min:8'],
        ]);
        if ($user->is(auth()->user()) && (! $data['is_active'] || $data['role'] !== RoleEnum::Administrateur->value)) {
            throw ValidationException::withMessages([
                'is_active' => 'Vous ne pouvez pas désactiver votre propre compte ni retirer votre rôle administrateur.',
            ]);
        }

        if ($data['is_active'] && ! $user->is_active) {
            $limits->ensureCanAddUser(Company::current());
        }

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'is_active' => $data['is_active'],
            ...(! empty($data['password']) ? ['password' => $data['password']] : []),
        ]);
        $user->syncRoles([$data['role']]);

        return to_route('administration.users.index')->with('success', 'L’utilisateur a été mis à jour.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->ensureSameCompany($user);

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->forceFill([
            'password' => $data['password'],
            'must_change_password' => true,
        ])->save();

        return to_route('administration.users.edit', $user)
            ->with('success', 'Le mot de passe provisoire a été défini. L’utilisateur devra le changer à sa prochaine connexion.');
    }

    /**
     * Users carry no global scope (authentication needs to find them before
     * any company is known), so an administrator only reaches the users of
     * their own company through this check.
     */
    private function ensureSameCompany(User $user): void
    {
        abort_unless($user->company_id === Company::current()->id, 404);
    }
}
