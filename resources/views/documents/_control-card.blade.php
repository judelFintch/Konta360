{{-- Code de contrôle et lien de vérification. Paramètres : $fingerprint, $verificationUrl. --}}
<article class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Contrôle du document</p>
    @if ($fingerprint)
        <p class="mt-2 font-mono text-base font-semibold tracking-wider text-gray-900">{{ $fingerprint }}</p>
        <p class="mt-1 text-xs text-gray-500">Code imprimé sur le document, avec un QR code de vérification.</p>
        <a href="{{ $verificationUrl }}" target="_blank" class="mt-3 inline-flex text-sm font-semibold text-indigo-600 hover:text-indigo-500">Ouvrir la page de vérification →</a>
    @else
        <p class="mt-2 text-sm text-gray-500">Le code de contrôle et le QR code sont attribués à la validation.</p>
    @endif
</article>
