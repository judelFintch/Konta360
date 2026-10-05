# ADR 0001 — Rôles, permissions et implémentation RBAC

- **Statut** : Accepté, matrice de permissions par rôle **provisoire** (voir section Validation requise)
- **Date** : 2026-07-29

## Contexte

Le cahier des charges (v1.0, 2026-07-17) définit 4 rôles utilisateurs mais ne fournit pas de matrice
détaillée « rôle × permission » :

- **Administrateur / Responsable comptable** : accès total, paramétrage, clôture d'exercice.
- **Comptable** : saisie, facturation, lettrage, édition d'états.
- **Direction / Gérance** : lecture des états financiers, validation de certaines écritures.
- **Utilisateur commercial** (optionnel) : accès restreint à la facturation.

Le module Administration doit fournir dès la Phase 0 un socle RBAC exploitable par tous les modules
futurs (Facturation, Comptabilité générale, Trésorerie, Immobilisations), sans qu'aucun module ne
code en dur des vérifications sur un nom de rôle.

## Décision

1. **`spatie/laravel-permission`** gère le stockage rôles/permissions (déjà en dépendance, tables
   migrées via `2026_07_28_153152_create_permission_tables`, mode "teams" désactivé — une seule
   société).
2. Les clés techniques sont des **enums PHP backés**, jamais des chaînes en dur dans le code métier :
   - `App\Modules\Administration\Enums\Permission` — une case par permission technique, groupée par
     module (Facturation, Tiers, Comptabilité générale, Trésorerie, Immobilisations, États
     financiers, Transversal).
   - `App\Modules\Administration\Enums\Role` — une case par rôle du cahier des charges, avec un
     libellé d'affichage FR via `label()`.
3. Le code applicatif (Gates, Policies, middleware de route) **doit toujours vérifier une
   `Permission`**, jamais `hasRole()` directement — les rôles ne sont qu'un regroupement de
   permissions, administrable plus tard via l'UI `settings.manage`.
4. `App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder` :
   - crée toutes les permissions à partir de `Permission::values()` ;
   - crée les 4 rôles à partir de `Role::values()` ;
   - assigne à chaque rôle un sous-ensemble de permissions (matrice ci-dessous) ;
   - est idempotent (`findOrCreate` / `syncPermissions`), donc rejouable sans erreur.
5. `DatabaseSeeder` appelle ce seeder et assigne le rôle `Administrateur` à l'utilisateur de test.

> **Amendement (ADR 0002, 2026-10-05)** : Konta360 est désormais multi-sociétés. Le mode « teams »
> reste désactivé : un utilisateur n'appartient qu'à une seule société, donc ses rôles ne valent que
> pour elle, et les 4 rôles ci-dessous sont communs à toutes les sociétés. L'administrateur de la
> plateforme n'est pas un rôle Spatie (`users.is_platform_admin`). Voir
> [ADR 0002](0002-multi-societes.md) § 8.

## Matrice de permissions par rôle (provisoire)

| Permission | Administrateur | Comptable | Direction | Commercial |
|---|:---:|:---:|:---:|:---:|
| invoices.view / create / update_draft | ✅ | ✅ | lecture seule | ✅ |
| invoices.validate / cancel | ✅ | ✅ | ❌ | ❌ |
| quotes.* | ✅ | ✅ | lecture seule | ✅ |
| credit_notes.create | ✅ | ✅ | ❌ | ❌ |
| payments.record / reverse | ✅ | ✅ | ❌ | ❌ |
| catalog.manage | ✅ | ✅ | ❌ | ❌ |
| parties.manage | ✅ | ✅ | ❌ | ✅ |
| accounting.view | ✅ | ✅ | ✅ | ❌ |
| accounting.entries.create | ✅ | ✅ | ❌ | ❌ |
| accounting.entries.post | ✅ | ✅ | ✅ (validation) | ❌ |
| accounting.periods.close | ✅ | ❌ | ❌ | ❌ |
| treasury.manage | ✅ | ✅ | ❌ | ❌ |
| fixed_assets.manage | ✅ | ✅ | ❌ | ❌ |
| financial_statements.view | ✅ | ✅ | ✅ | ❌ |
| reports.export | ✅ | ✅ | ✅ | ❌ |
| settings.manage | ✅ | ❌ | ❌ | ❌ |
| users.manage | ✅ | ❌ | ❌ | ❌ |
| audit.view | ✅ | ❌ | ✅ | ❌ |

Raisonnement :
- **Administrateur** obtient toutes les permissions (superset), conformément à « accès total ».
- **Comptable** obtient toutes les permissions opérationnelles (facturation, tiers, comptabilité,
  trésorerie, immobilisations, états) mais pas les leviers d'administration système
  (`settings.manage`, `users.manage`, `audit.view`) ni la clôture d'exercice, explicitement
  rattachée à l'Administrateur/Responsable comptable dans le cahier des charges.
- **Direction** est en lecture sur la facturation et la comptabilité, mais garde
  `accounting.entries.post` pour la « validation de certaines écritures » mentionnée au cahier des
  charges, et `audit.view` pour la supervision.
- **Commercial** est limité à la facturation (devis, factures en brouillon, ses tiers) sans accès à
  la validation finale des factures, aux règlements ni à la comptabilité.

## Validation requise

Cette matrice est une **interprétation raisonnable mais non validée** des descriptions de rôles du
cahier des charges — celui-ci ne fournit pas de tableau croisé rôle/permission explicite. Comme pour
le plan comptable ([voir mémoire chart-of-accounts-status]), elle doit être revue et confirmée par le
client avant mise en production, en particulier :
- si `Direction` doit pouvoir valider *toutes* les écritures ou seulement certaines (le cahier des
  charges dit « certaines écritures » sans préciser le critère de sélection) ;
- si `Commercial` doit pouvoir consulter le catalogue sans le gérer (permission `catalog.manage`
  actuellement absente pour ce rôle, alors que la création de devis/factures en dépend
  fonctionnellement — à trancher : soit une permission `catalog.view` séparée, soit un accès
  implicite via `invoices.create`/`quotes.create`).

## Conséquences

- Tout nouveau module doit ajouter ses permissions dans `Permission` (jamais de chaîne en dur) et
  étendre la matrice du seeder + ce document.
- Les tests (`tests/Feature/Administration/RolesAndPermissionsSeederTest.php`) figent la matrice
  actuelle : toute modification de la matrice doit s'accompagner d'une mise à jour des tests et de ce
  document dans le même changement.
