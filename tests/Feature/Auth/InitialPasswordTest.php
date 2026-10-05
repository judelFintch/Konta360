<?php

use App\Models\User;
use App\Modules\Administration\Database\Seeders\AdminUserSeeder;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;

function initialAdmin(): User
{
    return User::factory()->create(['company_id' => null, 'is_platform_admin' => true, 'must_change_password' => true]);
}

test('temporary password blocks platform access and livewire actions', function () {
    $this->actingAs(initialAdmin());
    $this->get(route('platform.dashboard'))->assertRedirect(route('password.initial'));
    $this->post('/livewire/update', [])->assertRedirect();
    $this->get(route('password.initial'))->assertOk()->assertSee('Choisissez votre mot de passe');
});

test('changing the temporary password unlocks the platform', function () {
    $user = initialAdmin();
    $this->actingAs($user)->post(route('password.initial.update'), [
        'current_password' => 'password', 'password' => 'New-secret-123!', 'password_confirmation' => 'New-secret-123!',
    ])->assertRedirect(route('platform.dashboard'));
    expect($user->fresh()->must_change_password)->toBeFalse();
    expect(Hash::check('New-secret-123!', $user->fresh()->password))->toBeTrue();
    $this->get(route('platform.dashboard'))->assertOk();
});

test('the temporary password cannot be reused and current password is checked', function () {
    $user = initialAdmin();
    $this->actingAs($user)->post(route('password.initial.update'), [
        'current_password' => 'password', 'password' => 'password', 'password_confirmation' => 'password',
    ])->assertSessionHasErrors('password');
    $this->post(route('password.initial.update'), [
        'current_password' => 'wrong', 'password' => 'New-secret-123!', 'password_confirmation' => 'New-secret-123!',
    ])->assertSessionHasErrors('current_password');
    expect($user->fresh()->must_change_password)->toBeTrue();
});

test('seeder creates a platform admin and preserves a changed password on rerun', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(AdminUserSeeder::class);
    $user = User::where('email', config('konta360.admin.email'))->firstOrFail();
    expect($user->is_platform_admin)->toBeTrue()->and($user->company_id)->toBeNull()
        ->and($user->must_change_password)->toBeTrue();
    $user->forceFill(['password' => 'Changed-secret-123!', 'must_change_password' => false])->save();
    $this->seed(AdminUserSeeder::class);
    expect($user->fresh()->must_change_password)->toBeFalse();
    expect(Hash::check('Changed-secret-123!', $user->fresh()->password))->toBeTrue();
});
