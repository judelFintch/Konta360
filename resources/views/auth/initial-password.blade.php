@component('layouts.showcase')
    <div class="mb-6 rounded-xl border border-indigo-100 bg-indigo-50 p-4 text-sm text-indigo-900">
        Pour sécuriser votre compte, remplacez votre mot de passe temporaire avant de continuer.
    </div>
    <h2 class="text-2xl font-bold text-gray-900">Choisissez votre mot de passe</h2>
    <form method="POST" action="{{ route('password.initial.update') }}" class="mt-6 space-y-5">
        @csrf
        @foreach (['current_password' => 'Mot de passe temporaire', 'password' => 'Nouveau mot de passe', 'password_confirmation' => 'Confirmez le nouveau mot de passe'] as $field => $label)
            <div>
                <x-input-label :for="$field" :value="$label" />
                <x-text-input :id="$field" :name="$field" type="password" class="mt-1 block w-full" required autocomplete="{{ $field === 'current_password' ? 'current-password' : 'new-password' }}" />
                <x-input-error :messages="$errors->get($field)" class="mt-2" />
            </div>
        @endforeach
        <p class="text-xs text-gray-500">Utilisez au moins 8 caractères et un mot de passe différent du mot de passe temporaire.</p>
        <button class="w-full rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white hover:bg-indigo-500">Enregistrer et continuer</button>
    </form>
@endcomponent
