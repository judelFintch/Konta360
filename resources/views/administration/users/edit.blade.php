<x-app-layout>
    <x-slot name="header"><div><p class="text-sm font-medium text-indigo-600">Administration</p><h1 class="text-2xl font-semibold text-gray-900">Gérer {{ $managedUser->name }}</h1></div></x-slot>
    <div class="py-10">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
            @endif
            <form method="POST" action="{{ route('administration.users.update',$managedUser) }}" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">
                @csrf
                @method('PUT')
                @include('administration.users._form',['submitLabel'=>'Enregistrer'])
            </form>

            <form method="POST" action="{{ route('administration.users.password.reset',$managedUser) }}" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">
                @csrf
                <h2 class="text-lg font-semibold text-gray-900">Réinitialiser le mot de passe</h2>
                <p class="mt-1 text-sm text-gray-600">Définissez un mot de passe provisoire à communiquer à l’utilisateur. Il devra le changer à sa prochaine connexion.</p>
                <div class="mt-5 grid gap-6 sm:grid-cols-2">
                    <div>
                        <x-input-label for="reset_password" value="Mot de passe provisoire" />
                        <x-text-input id="reset_password" name="password" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="reset_password_confirmation" value="Confirmer le mot de passe" />
                        <x-text-input id="reset_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <x-primary-button>Réinitialiser le mot de passe</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
