@if ($errors->has('mail') || session('mail_error'))
    <div role="alert" aria-live="assertive" class="mb-6 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900">
        <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9 2 18.2A1.9 1.9 0 0 0 3.7 21h16.6a1.9 1.9 0 0 0 1.7-2.8L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
        <div>
            <p class="text-sm font-semibold">Votre code n’a pas pu être envoyé</p>
            <p class="mt-1 text-sm leading-6">{{ $errors->first('mail') ?: session('mail_error') }}</p>
        </div>
    </div>
@endif
