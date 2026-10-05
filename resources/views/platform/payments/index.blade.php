@extends('layouts.platform')

@section('title', 'Paiements')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Paiements d’abonnement</h1>
            <p class="mt-1 text-sm text-gray-500">Vérifiez chaque référence sur le relevé mobile money ou bancaire avant de confirmer : la confirmation prolonge l’abonnement.</p>
        </div>
        <nav class="flex gap-1 rounded-lg bg-white p-1 shadow-sm">
            @foreach (\App\Modules\Billing\Enums\SubscriptionPaymentStatus::cases() as $case)
                <a href="{{ route('platform.payments.index', ['status' => $case->value]) }}" class="rounded-md px-3 py-1.5 text-sm font-medium {{ $status === $case ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:text-gray-900' }}">{{ $case->label() }}</a>
            @endforeach
        </nav>
    </div>

    <div class="mt-6 overflow-x-auto rounded-lg bg-white shadow">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3">Déclaré le</th>
                    <th class="px-4 py-3">Société</th>
                    <th class="px-4 py-3">Formule</th>
                    <th class="px-4 py-3 text-right">Montant</th>
                    <th class="px-4 py-3">Moyen et référence</th>
                    <th class="px-4 py-3">{{ $status === \App\Modules\Billing\Enums\SubscriptionPaymentStatus::Pending ? '' : 'Traitement' }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($payments as $payment)
                    <tr class="align-top">
                        <td class="px-4 py-3">{{ $payment->created_at->format('d/m/Y H:i') }}<p class="text-xs text-gray-500">par {{ $payment->submitter?->name }}</p></td>
                        <td class="px-4 py-3 font-medium">{{ $payment->company->name }}</td>
                        <td class="px-4 py-3">{{ $payment->plan->name }} · {{ $payment->months }} mois</td>
                        <td class="px-4 py-3 text-right font-medium">{{ number_format((float) $payment->amount, 2, ',', ' ') }} {{ $payment->currency }}</td>
                        <td class="px-4 py-3">{{ $payment->method->label() }}<p class="font-mono text-xs text-gray-600">{{ $payment->reference }}</p></td>
                        <td class="px-4 py-3">
                            @if ($payment->status === \App\Modules\Billing\Enums\SubscriptionPaymentStatus::Pending)
                                <div class="flex flex-col items-end gap-2">
                                    <form method="POST" action="{{ route('platform.payments.confirm', $payment->id) }}" onsubmit="return confirm('Confirmer la réception de ce paiement ?')">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500">Confirmer</button>
                                    </form>
                                    <form method="POST" action="{{ route('platform.payments.reject', $payment->id) }}" class="flex gap-2">
                                        @csrf @method('PATCH')
                                        <input name="rejection_reason" required minlength="3" placeholder="Motif du refus" class="w-40 rounded-md border-gray-300 text-xs">
                                        <button type="submit" class="text-xs font-medium text-red-600 hover:text-red-500">Refuser</button>
                                    </form>
                                </div>
                            @elseif ($payment->status === \App\Modules\Billing\Enums\SubscriptionPaymentStatus::Confirmed)
                                Du {{ $payment->period_starts_on->format('d/m/Y') }} au {{ $payment->period_ends_on->format('d/m/Y') }}
                                <p class="text-xs text-gray-500">par {{ $payment->reviewer?->name }}, le {{ $payment->reviewed_at->format('d/m/Y') }}</p>
                            @else
                                {{ $payment->rejection_reason }}
                                <p class="text-xs text-gray-500">par {{ $payment->reviewer?->name }}, le {{ $payment->reviewed_at->format('d/m/Y') }}</p>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">Aucun paiement.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $payments->links() }}</div>
@endsection
