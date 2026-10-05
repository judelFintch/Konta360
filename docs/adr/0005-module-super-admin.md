# ADR 0005 — Module super admin : suivi et contrôle des abonnés

- **Statut** : Accepté et implémenté
- **Date** : 2026-10-08
- **Complète** : [ADR 0002](0002-multi-societes.md) § 8 et [ADR 0003](0003-abonnements-et-donnees.md)

## Contexte

L'espace plateforme permettait déjà de lister les sociétés, de les suspendre, de leur offrir
l'accès, de confirmer les paiements et de modifier les formules. Il manquait de quoi **suivre**
les abonnés : qui paie, qui expire bientôt, qui utilise vraiment l'application. Il manquait aussi
des actions courantes sans passer par la base de données, et une trace de ce que fait l'équipe
Konta360.

## Décision

1. **Tableau de bord** (`/platform`). La page d'arrivée de l'administrateur de la plateforme
   affiche :
   - le nombre de sociétés par statut d'abonnement ;
   - le revenu mensuel récurrent, c'est-à-dire le prix mensuel des formules des abonnés payants,
     par devise ;
   - les montants encaissés dans le mois ;
   - les paiements à vérifier, les clôtures demandées et les sociétés suspendues ;
   - les accès qui se terminent dans les 7 jours ;
   - les dernières inscriptions et les dernières actions de la plateforme.
2. **Liste des abonnés** (`/platform/companies`) :
   - filtres par statut, formule et accès (suspendue, clôture demandée, clôturée) ;
   - recherche par nom, NIF ou e-mail d'un utilisateur ;
   - tri par date, échéance, dernière connexion ou nom ;
   - export CSV de la liste filtrée.

   Le filtre par statut repose sur le scope SQL `Company::withSubscriptionStatus()`, qui applique
   les mêmes règles que `Company::subscriptionStatus()`.
3. **Fiche abonné** (`/platform/companies/{id}`). Elle présente :
   - l'identité de la société et ses coordonnées ;
   - son abonnement et l'usage des limites ;
   - ses utilisateurs, avec leur rôle et leur dernière connexion ;
   - ses paiements ;
   - des notes internes (`companies.platform_notes`) ;
   - l'historique des actions de la plateforme sur cette société.

   **Aucune donnée comptable** n'y figure, conformément à l'ADR 0002 § 8. Le nombre de factures
   validées dans le mois est le seul chiffre lu « en tant que » la société, et c'est un simple
   comptage.
4. **Actions** (`CompanySubscriptionController`) :
   - changer de formule, sans paiement ni changement de dates ;
   - prolonger l'évaluation ; une évaluation déjà expirée repart d'aujourd'hui ;
   - enregistrer un paiement reçu directement, qui est déclaré et confirmé en une seule étape, avec
     les mêmes règles de période que les paiements déclarés ;
   - désactiver ou réactiver un utilisateur, uniquement au sein de la société concernée.
5. **Journal de la plateforme** (`platform_events`). Chaque action d'un opérateur y est
   enregistrée avec son auteur et sa date : suspension, rétablissement, accès offert, clôture,
   changement de formule, prolongation, paiement confirmé, refusé ou enregistré, utilisateur
   désactivé, formule modifiée. Ce journal n'est pas une donnée de la société : il n'est ni montré
   à la société, ni exporté, et il est supprimé avec elle.
6. **Dernière connexion** (`users.last_login_at`) : enregistrée lorsque le code de connexion est
   validé (ADR 0004).

## Conséquences

- Toute nouvelle action de la plateforme sur une société doit appeler `PlatformEvent::record()`.
- Les pages et actions du module sont réservées aux administrateurs de la plateforme (middleware
  `platform`). `SuperAdminTest` vérifie qu'un utilisateur de société reçoit 403 sur chacune
  d'elles.
- Le revenu mensuel récurrent est une estimation : il ne tient pas compte des paiements couvrant
  plusieurs mois ni des accès offerts.
