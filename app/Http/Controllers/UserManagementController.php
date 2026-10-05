<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use App\Modules\Administration\Enums\Permission;
use App\Modules\Administration\Enums\Role as RoleEnum;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $users = User::query()->whereBelongsTo(Company::current())->with('roles')
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderBy('name')->paginate(20)->withQueryString();

        return view('administration.users.index', compact('users', 'search'));
    }

    public function create(): View
    {
        return view('administration.users.create', ['roles' => RoleEnum::cases()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
            'role' => ['required', Rule::enum(RoleEnum::class)],
        ]);
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

        return view('administration.users.edit', [
            'managedUser' => $user->load('roles'),
            'roles' => RoleEnum::cases(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
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

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'is_active' => $data['is_active'],
            ...(! empty($data['password']) ? ['password' => $data['password']] : []),
        ]);
        $user->syncRoles([$data['role']]);

        return to_route('administration.users.index')->with('success', 'L’utilisateur a été mis à jour.');
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
