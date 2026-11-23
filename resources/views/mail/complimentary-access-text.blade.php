Bonjour {{ $user->name }},

@if ($granted)
Bonne nouvelle : l’équipe Konta360 offre à {{ $company->name }} l’accès complet à Konta360.

- toutes les fonctionnalités : devis, factures, règlements, comptabilité, trésorerie ;
- sans limite d’utilisateurs ni de factures ;
- aucun abonnement à payer.
@else
L’accès offert dont bénéficiait {{ $company->name }} sur Konta360 a pris fin.

@if ($canStillWrite && $accessEndsOn)
Votre accès continue normalement jusqu’au {{ $accessEndsOn->format('d/m/Y') }}. Pensez à choisir une formule avant cette date.
@elseif ($canStillWrite)
Votre accès continue normalement selon votre abonnement en cours.
@else
Vos données restent consultables et exportables, mais la création et la modification sont suspendues jusqu’à la souscription d’une formule.
@endif
@endif

{{ $actionLabel }} : {{ $actionUrl }}

@if ($contact)
Une question ? Écrivez-nous à {{ $contact }}.
@else
Une question ? Répondez simplement à cet e-mail.
@endif

— L’équipe Konta360
