# ADR 0003 — Abonnements, CGU et cycle de vie des données d'une société

- **Statut** : Accepté et implémenté. Les prix, les limites et les textes juridiques sont
  **provisoires** (voir Validation requise).
- **Date** : 2026-10-06
- **Complète** : [ADR 0002](0002-multi-societes.md), qui laissait hors périmètre l'abonnement, les
  limites par formule, les CGU et l'export ou la suppression des données.

## Contexte

Depuis l'ADR 0002, n'importe quelle société peut s'inscrire. Il reste à décider :

1. comment elle paie son abonnement ;
2. ce qui se passe quand elle ne paie plus ;
3. ce que chaque formule permet ;
4. ce qu'elle accepte en s'inscrivant ;
5. comment elle récupère ses données, puis comment elle les fait supprimer.

Contraintes :

- hébergement cPanel, sans worker permanent ;
- clientèle en RDC, où les paiements passent surtout par mobile money et virement ;
- droit comptable OHADA, qui impose de conserver les pièces comptables dix ans.

## Décision

### 1. Essai et formules

- La table `plans` est commune à toutes les sociétés. Chaque formule a un code, un prix mensuel, une
  devise et deux limites : utilisateurs actifs et factures validées par mois. Une limite `null`
  signifie illimité. Les formules se modifient dans `/platform/plans`.
- Trois formules sont créées par la migration, avec des **prix provisoires** :
  - Essentiel : 15 USD, 2 utilisateurs, 50 factures par mois ;
  - Pro : 35 USD, 5 utilisateurs, 300 factures par mois ;
  - Entreprise : 75 USD, illimitée.
- À l'inscription, la société choisit une formule et démarre un **essai gratuit** de
  `konta360.billing.trial_days` jours (30), jour d'inscription compris (`companies.trial_ends_at`).
- `Company::subscriptionStatus()` déduit le statut des dates, sans tâche planifiée : `Exempt`
  (accès offert), `Active` (jours payés), `Trial` (essai) ou `Expired`.
- Les sociétés antérieures aux abonnements sont passées en **accès offert** (`billing_exempt`) sur la
  formule Entreprise. La plateforme peut retirer ou accorder cet accès.

### 2. Paiement manuel, confirmé par la plateforme

Aucune passerelle de paiement pour l'instant :

1. l'administrateur de la société paie hors de l'application, par mobile money, virement ou
   espèces ;
2. il déclare le paiement dans `/subscription` : formule, durée (1, 3, 6 ou 12 mois), moyen et
   référence. **Le montant est toujours calculé à partir de la formule**, jamais repris du
   formulaire ;
3. l'opérateur Konta360 le vérifie sur son relevé, puis le confirme ou le refuse avec un motif dans
   `/platform/payments`.

À la confirmation (`SubscriptionManager::confirm`, en transaction avec verrou), la période payée
commence au plus tard des trois dates suivantes : aujourd'hui, le lendemain de la fin d'abonnement,
ou le lendemain de la fin d'essai. **Aucun jour déjà acquis n'est perdu.** La formule de la société
devient celle du paiement. Un paiement n'est traité qu'une fois. Il n'y a pas de prorata en cas de
changement de formule.

Une passerelle (FlexPay, CinetPay, MaxiCash…) pourra appeler `SubscriptionManager::confirm()` depuis
un webhook, sans changer le reste.

### 3. Expiration : lecture seule

Le middleware `EnsureWritableSubscription` (groupe `web`, avant le journal d'audit) agit sur une
société au statut `Expired`. Il refuse toute requête d'écriture (POST, PUT, PATCH, DELETE) et
redirige vers `/subscription` avec un bandeau.

Restent permis :
- le renouvellement (`subscription.*`) ;
- les données de la société (`administration.data.*`) : export, CGU, clôture ;
- les requêtes Livewire : déconnexion et profil.

La consultation et l'export restent toujours possibles. Une société qui ne paie plus garde ainsi
accès à ses pièces comptables, qu'elle est tenue de conserver.

Un bandeau prévient 7 jours avant la fin de l'essai ou de l'abonnement.

### 4. Limites des formules

`PlanLimits` vérifie deux limites. Elles ne s'appliquent ni à une société en accès offert ni à une
société sans formule.

- **Utilisateurs actifs** : vérifiée à la création d'un utilisateur et à la réactivation d'un compte.
  Un changement de formule à la baisse ne désactive personne ; il empêche seulement d'en ajouter.
- **Factures validées dans le mois civil** : vérifiée à la validation, qui est le moment de la
  numérotation. Les brouillons, devis et avoirs ne sont pas limités.

### 5. CGU et politique de confidentialité

- Les pages publiques `/legal/terms` et `/legal/privacy` sont des **projets non validés**, signalés
  comme tels sur la page.
- Leur version est `konta360.terms_version`. L'inscription exige de les accepter, et la société
  enregistre la version, la date et l'utilisateur.
- Quand la version change, les administrateurs voient un bandeau et les acceptent depuis
  Administration › Données et confidentialité. L'accès n'est pas bloqué.

### 6. Export, clôture et purge

- **Export** (`CompanyDataExporter`) : une archive ZIP téléchargeable à tout moment par un
  administrateur, même en lecture seule. Elle contient :
  - un CSV par table métier, filtré explicitement sur `company_id`, au même format que les autres
    exports (UTF-8 avec BOM, séparateur « ; », formules neutralisées) ;
  - la liste des utilisateurs, sans mot de passe ;
  - les paramètres de la société et ses fichiers (logo, signature, cachet).
- **Demande de clôture** : faite par l'administrateur, qui confirme avec son mot de passe et la
  raison sociale exacte. Elle est annulable tant que la plateforme ne l'a pas traitée.
- **Clôture** : prononcée par la plateforme, uniquement sur demande. Elle suspend l'accès
  (`closed_at` et `suspended_at`) et ne peut pas être annulée depuis l'interface. Les données sont
  **conservées** pendant `konta360.retention_years` ans (10).
- **Purge** : la commande `php artisan konta360:purge-company {id}` supprime définitivement la
  société clôturée et tout ce qu'elle possède : tables métier dans l'ordre des clés étrangères,
  utilisateurs, rôles, sessions et fichiers. Elle refuse de s'exécuter avant la fin de la durée de
  conservation. `--ignore-retention` est prévu pour une inscription de test vide et demande une
  confirmation explicite.

### 7. Accès inter-sociétés de la plateforme

Les paiements d'abonnement portent le trait `BelongsToCompany`. La plateforme, qui n'a pas de
société courante, les lit avec `withoutGlobalScope(CompanyScope::class)` dans
`SubscriptionPaymentController`, `SubscriptionManager` et le compteur du menu de la plateforme. Ce
sont, avec la page publique de vérification, les seuls contournements du scope (ADR 0002 § 4).

## Validation requise

- **Prix et limites** des trois formules, et devise de facturation (USD).
- **Textes des CGU et de la politique de confidentialité**, à faire relire par un juriste au regard du
  droit congolais. Retirer ensuite le bandeau « Projet » de `resources/views/legal/layout.blade.php`.
- **Durée de conservation** de 10 ans après clôture, à confirmer.
- **Coordonnées de paiement** à renseigner dans `.env` : `BILLING_MOBILE_MONEY`,
  `BILLING_BANK_ACCOUNT` et `BILLING_CONTACT_EMAIL`.
- **Facturation de l'abonnement** : Konta360 n'émet pas encore de facture pour l'abonnement payé par
  la société. À prévoir si les clients en ont besoin pour leur propre comptabilité.
- **Rappels par e-mail** avant expiration : non implémentés. Ils demandent une tâche planifiée
  (cron cPanel).

## Conséquences

- **Toute nouvelle route d'écriture** est bloquée pour une société expirée, sauf si elle est ajoutée
  à `EnsureWritableSubscription::ALWAYS_ALLOWED`. Ce choix doit être délibéré.
- **Toute nouvelle table métier** doit être ajoutée à `CompanyDataExporter::TABLES` et à
  `CompanyPurger::TABLES`, cette dernière dans l'ordre des clés étrangères. Le test de purge vérifie
  que chaque table exportée est bien vidée.
- `CompanyFactory` crée une société en période d'essai. L'état `expired()` sert à tester la lecture
  seule.
