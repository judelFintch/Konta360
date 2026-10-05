<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class InitialPasswordController extends Controller
{
    public function edit(Request $request)
    {
        if (! $request->user()->must_change_password) {
            return redirect()->route('dashboard');
        }

        return view('auth.initial-password');
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (Hash::check($data['password'], $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Choisissez un mot de passe différent du mot de passe temporaire.']);
        }

        $request->user()->forceFill([
            'password' => $data['password'],
            'must_change_password' => false,
            'remember_token' => Str::random(60),
        ])->save();
        $request->session()->regenerate();

        return redirect()->route($request->user()->is_platform_admin ? 'platform.dashboard' : 'dashboard');
    }
}
