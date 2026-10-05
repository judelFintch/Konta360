@extends('legal.layout')

@section('title', 'Conditions générales d’utilisation')

@section('content')
    <h1>Conditions générales d’utilisation</h1>

    <h2>1. Objet</h2>
    <p>Konta360 est un service en ligne de facturation et de comptabilité. Les présentes conditions régissent l’utilisation du service par la société qui s’inscrit (le « Client ») et par les utilisateurs qu’elle désigne.</p>

    <h2>2. Compte et utilisateurs</h2>
    <ul>
        <li>La personne qui inscrit la société déclare être habilitée à l’engager. Elle devient administrateur du compte.</li>
        <li>L’administrateur crée et gère les utilisateurs de la société et leurs droits. Le Client répond de leurs actions.</li>
        <li>Chaque utilisateur garde ses identifiants confidentiels et signale sans délai toute utilisation non autorisée.</li>
    </ul>

    <h2>3. Essai, abonnement et paiement</h2>
    <ul>
        <li>Une période d’essai gratuite de {{ config('konta360.billing.trial_days') }} jours est offerte à l’inscription, sans engagement.</li>
        <li>L’abonnement est payé d’avance, pour la durée choisie, au prix de la formule en vigueur au moment du paiement. Il prend effet à la confirmation du paiement par Konta360 et s’ajoute à la période d’essai ou d’abonnement restante.</li>
        <li>Les limites de chaque formule (nombre d’utilisateurs, nombre de factures validées par mois) sont indiquées sur la page Abonnement.</li>
        <li>À l’expiration de l’essai ou de l’abonnement, le compte passe en lecture seule : le Client peut toujours consulter et exporter ses données, mais ne peut plus en créer ni en modifier jusqu’au renouvellement.</li>
    </ul>

    <h2>4. Données du Client</h2>
    <ul>
        <li>Le Client reste propriétaire de ses données. Il peut à tout moment les exporter dans un format ouvert (CSV) depuis Administration › Données et confidentialité.</li>
        <li>Le Client est responsable de l’exactitude de ses écritures et de ses déclarations fiscales et sociales. Konta360 est un outil : il ne remplace ni un expert-comptable ni un conseil juridique.</li>
        <li>Le plan comptable et les règles de calcul proposés sont fournis à titre indicatif et doivent être vérifiés par le Client.</li>
    </ul>

    <h2>5. Disponibilité</h2>
    <p>Konta360 s’efforce d’assurer un accès continu au service, sans garantie d’absence d’interruption, notamment pour maintenance. Il est recommandé au Client de réaliser régulièrement des exports de ses données.</p>

    <h2>6. Suspension</h2>
    <p>Konta360 peut suspendre l’accès d’un Client en cas de manquement grave aux présentes conditions, d’utilisation frauduleuse ou de défaut de paiement prolongé, après en avoir informé le Client sauf urgence.</p>

    <h2>7. Clôture du compte</h2>
    <ul>
        <li>L’administrateur peut demander la clôture du compte depuis Administration › Données et confidentialité.</li>
        <li>Après clôture, plus aucun utilisateur ne peut se connecter. Les pièces comptables devant être conservées {{ config('konta360.retention_years') }} ans en application du droit comptable OHADA, les données sont conservées pendant cette durée puis supprimées définitivement.</li>
        <li>Les sommes déjà payées pour une période en cours ne sont pas remboursées, sauf accord particulier.</li>
    </ul>

    <h2>8. Confidentialité des données personnelles</h2>
    <p>Le traitement des données personnelles est décrit dans la <a href="{{ route('legal.privacy') }}" class="font-medium text-indigo-600">politique de confidentialité</a>.</p>

    <h2>9. Modification des conditions</h2>
    <p>Konta360 peut faire évoluer les présentes conditions. Les administrateurs sont invités à accepter chaque nouvelle version depuis l’application.</p>

    <h2>10. Contact</h2>
    <p>Pour toute question : {{ config('konta360.billing.payment_instructions.contact') ?: 'l’adresse de contact indiquée par Konta360' }}.</p>
@endsection
