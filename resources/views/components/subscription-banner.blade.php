@php
    use App\Modules\Administration\Enums\Permission;
    use App\Modules\Billing\Enums\SubscriptionStatus;

    $company = \App\Models\Company::current();
    $status = $company->subscriptionStatus();
    $daysLeft = $company->accessEndsOn() ? (int) today()->diffInDays($company->accessEndsOn()) + 1 : null;
    $canManage = auth()->user()->can(Permission::SettingsManage->value);
@endphp

@if (session('subscription_blocked') || $status === SubscriptionStatus::Expired)
    <div class="bg-red-600 px-4 py-2 text-center text-sm text-white">
        {{ session('subscription_blocked', 'Votre essai ou votre abonnement a expiré : votre accès est en lecture seule.') }}
        <a href="{{ route('subscription.show') }}" class="font-semibold underline">Renouveler</a>
    </div>
@elseif ($status === SubscriptionStatus::Trial && $daysLeft !== null && $daysLeft <= 7)
    <div class="bg-amber-500 px-4 py-2 text-center text-sm text-white">
        Votre essai gratuit se termine dans {{ $daysLeft }} jour(s).
        <a href="{{ route('subscription.show') }}" class="font-semibold underline">Choisir une formule</a>
    </div>
@elseif ($status === SubscriptionStatus::Active && $daysLeft !== null && $daysLeft <= 7)
    <div class="bg-amber-500 px-4 py-2 text-center text-sm text-white">
        Votre abonnement se termine dans {{ $daysLeft }} jour(s).
        <a href="{{ route('subscription.show') }}" class="font-semibold underline">Le prolonger</a>
    </div>
@endif

@if ($canManage && ! $company->hasAcceptedCurrentTerms())
    <div class="bg-indigo-600 px-4 py-2 text-center text-sm text-white">
        Les conditions générales et la politique de confidentialité ont été mises à jour.
        <a href="{{ route('administration.data.show') }}" class="font-semibold underline">Les consulter et les accepter</a>
    </div>
@endif
