{{-- Statut du devis, avec l’expiration quand il attend encore une réponse. --}}
@php use App\Modules\Quotes\Enums\QuoteStatus; @endphp
@if ($quote->isExpired())
    <x-badge color="red">Expiré</x-badge>
@else
    <x-badge :color="match ($quote->status) {
        QuoteStatus::Draft => 'gray',
        QuoteStatus::Sent => 'indigo',
        QuoteStatus::Accepted => 'green',
        QuoteStatus::Rejected, QuoteStatus::Cancelled => 'red',
    }">{{ $quote->status->label() }}</x-badge>
@endif
