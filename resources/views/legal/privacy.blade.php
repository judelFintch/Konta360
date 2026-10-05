@extends('legal.layout')

@section('title', 'Politique de confidentialité')

@section('content')
    <h1>Politique de confidentialité</h1>

    <h2>1. Rôles</h2>
    <ul>
        <li>Pour les comptes utilisateurs, la facturation de l’abonnement et la sécurité du service, Konta360 décide de l’usage des données.</li>
        <li>Pour les données que le Client saisit dans sa comptabilité (clients, fournisseurs, factures, écritures…), Konta360 agit uniquement pour le compte du Client et selon ses instructions. Le Client reste responsable de ces données vis-à-vis des personnes concernées.</li>
    </ul>

    <h2>2. Données traitées</h2>
    <ul>
        <li><strong>Utilisateurs</strong> : nom, adresse e-mail, mot de passe (stocké sous forme chiffrée irréversible), rôle.</li>
        <li><strong>Journal d’audit</strong> : actions effectuées, date, adresse IP et navigateur, pour la traçabilité exigée en comptabilité et la sécurité.</li>
        <li><strong>Abonnement</strong> : formule, paiements déclarés et leurs références.</li>
        <li><strong>Données comptables du Client</strong> : informations sur ses tiers, documents commerciaux et écritures.</li>
    </ul>

    <h2>3. Finalités</h2>
    <p>Fournir le service, sécuriser les accès, tenir la piste d’audit, gérer l’abonnement et répondre aux obligations légales. Les données ne sont ni vendues ni utilisées à des fins publicitaires.</p>

    <h2>4. Isolation et accès</h2>
    <p>Les données de chaque société sont isolées de celles des autres sociétés. Seuls les utilisateurs désignés par le Client y accèdent. L’équipe d’exploitation de Konta360 n’accède pas aux données comptables du Client depuis son espace d’administration.</p>

    <h2>5. Durées de conservation</h2>
    <ul>
        <li>Données comptables et piste d’audit : pendant toute la durée du contrat, puis {{ config('konta360.retention_years') }} ans après la clôture du compte (obligation de conservation du droit comptable OHADA), avant suppression définitive.</li>
        <li>Comptes utilisateurs : jusqu’à la suppression définitive de la société, sauf désactivation antérieure par l’administrateur.</li>
    </ul>

    <h2>6. Vos droits</h2>
    <p>Vous pouvez demander l’accès à vos données personnelles, leur rectification ou, dans la limite des obligations de conservation, leur suppression. Le Client peut exporter l’ensemble de ses données à tout moment. Pour exercer vos droits : {{ config('konta360.billing.payment_instructions.contact') ?: 'l’adresse de contact indiquée par Konta360' }}.</p>

    <h2>7. Sécurité</h2>
    <p>Connexion chiffrée (HTTPS), mots de passe chiffrés, contrôle des droits par rôle, journal d’audit et isolation des données par société.</p>
@endsection
