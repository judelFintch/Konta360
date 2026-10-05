# ADR 0002 — Multi-sociétés (inscription libre, données isolées par société)

- **Statut** : Accepté et implémenté. Les choix marqués « par défaut » dans la section Validation
  requise restent à confirmer.
- **Date** : 2026-10-05
- **Amende** : [ADR 0001](0001-roles-and-permissions.md). Les rôles restent communs à toutes les
  sociétés et le mode « teams » de Spatie reste désactivé (voir § 8).

## Contexte

Konta360 a été conçu pour **une seule société** :

- `CompanySetting::current()` renvoyait `first()` ;
- aucune table métier ne portait d'identifiant de société ;
- le plan comptable et les journaux étaient insérés par les migrations ;
- les numéros de pièces étaient dérivés de l'`id` auto-incrémenté global.

Nouveau besoin : un client doit pouvoir **s'inscrire**, obtenir **sa propre société**, gérer **ses
propres utilisateurs** et tenir **sa propre comptabilité**, sans jamais voir ni toucher les données
d'une autre société.

Contrainte d'hébergement : déploiement sur **cPanel** (voir `docs/deployment-cpanel.md`), sans
création programmatique de bases de données ni worker permanent.

## Décision

### 1. Isolation : base unique + colonne `company_id`

Une seule base de données. Chaque table métier reçoit `company_id` : clé étrangère non nulle vers
`companies`, indexée, avec suppression interdite.

Alternative écartée : **une base par société** (ex. `stancl/tenancy`). Elle impose de créer une base
MySQL par inscription via l'API cPanel, de rejouer chaque migration N fois et de multiplier les
sauvegardes. C'est disproportionné pour le contexte actuel. La décision pourra être revue si un client
exige une isolation physique.

### 2. Modèle `Company`

- La nouvelle table `companies` **remplace `company_settings`**. La migration
  `2026_10_05_100000_create_companies_table` recopie la ligne existante en société n°1, rattache
  tous les utilisateurs existants à cette société, puis supprime `company_settings`.
- `App\Models\Company::current()` remplace `CompanySetting::current()` partout : contrôleurs, vues,
  PDF.
- Les valeurs par défaut de la table (préfixes, padding, devise…) sont aussi déclarées dans
  `$attributes`, pour qu'une société tout juste créée sache numéroter ses pièces.

### 3. Tables concernées

`company_id` est ajouté à toutes les tables métier, y compris les tables de lignes :

`parties`, `catalog_items`, `quotes`, `quote_lines`, `invoices`, `invoice_lines`,
`invoice_deductions`, `payments`, `credit_notes`, `credit_note_lines`, `accounts`, `journals`,
`accounting_entries`, `accounting_entry_lines`, `accounting_periods`, `fixed_assets`,
`fixed_asset_depreciations`, `treasury_accounts`, `treasury_transactions`, `bank_reconciliations`,
`expenses`, `expense_payments`, `audit_logs`.

Pour les tables de lignes, la dénormalisation est volontaire : le scope automatique couvre ainsi
toute requête directe sur une table enfant.

Exceptions :

- `bank_reconciliation_transactions` est une table pivot sans modèle, alimentée par `attach()`. Ses
  deux côtés appartiennent déjà à une société et sont vérifiés à la validation (§ 5).
- `users.company_id` est **nullable**, parce qu'un administrateur de la plateforme n'appartient à
  aucune société (§ 8).
- Les tables d'infrastructure (`cache`, `jobs`, `sessions`, `password_reset_tokens`, tables Spatie)
  ne sont pas concernées.

### 4. Mécanisme d'isolation applicative

- `App\Modules\Companies\Services\CurrentCompany` est un singleton qui détient la société courante.
  Sa lecture alors qu'elle n'est pas définie lève `MissingCompanyContext` : on échoue bruyamment, on
  ne renvoie jamais un résultat non filtré.
- Le middleware `App\Http\Middleware\SetCurrentCompany` est ajouté au groupe `web`. Il fixe la
  société depuis l'utilisateur connecté, déconnecte les utilisateurs d'une société suspendue et
  renvoie 403 pour un compte sans société qui n'est pas administrateur de la plateforme.
  **Il est prioritaire sur `SubstituteBindings`** : sans cela, la liaison des modèles de route
  s'exécuterait avant que la société soit connue. Un test l'a révélé pendant l'implémentation.
- Le trait `App\Modules\Companies\Concerns\BelongsToCompany`, posé sur chaque modèle métier :
  - ajoute le scope global `CompanyScope` (`where company_id = <société courante>`) ;
  - remplit `company_id` automatiquement à la création.
- **`User` n'a pas de scope global** : l'authentification doit trouver un utilisateur avant de
  connaître sa société. Les écrans qui listent ou modifient des utilisateurs filtrent explicitement :
  `whereBelongsTo(Company::current())` dans la liste, et un contrôle `ensureSameCompany()` (404) en
  édition.
- Le contournement du scope (`withoutGlobalScope(CompanyScope::class)`) n'est utilisé qu'à un seul
  endroit, la page publique de vérification (§ 10).
- Le provisionnement fixe `company_id` explicitement. `DatabaseSeeder` s'exécute sans événements de
  modèle, donc le remplissage automatique du trait ne s'y déclenche pas.

### 5. Règles de validation

Les règles `exists:` et `unique:` interrogent la base directement et **ne passent pas par le scope
Eloquent**. Les 18 occurrences sur des tables métier utilisent désormais
`App\Modules\Companies\Validation\CompanyRule` :

```php
CompanyRule::exists('parties')
CompanyRule::unique('catalog_items')->ignore($item)
```

Seule l'adresse e-mail reste unique sur toute la plateforme (table `users`).
`tests/Feature/Companies/ArchitectureTest.php` refuse toute nouvelle règle `exists:` ou `unique:`
non scopée.

### 6. Contraintes d'unicité composites

| Table | Avant | Après |
|---|---|---|
| quotes, invoices, credit_notes, payments, expenses, expense_payments, treasury_transactions, bank_reconciliations, accounting_entries | `number` | `(company_id, number)` |
| accounts, journals, fixed_assets | `code` | `(company_id, code)` |
| catalog_items | `sku` | `(company_id, sku)` |
| parties | `tax_identifier` | `(company_id, tax_identifier)` |
| accounting_periods | `(starts_on, ends_on)` | `(company_id, starts_on, ends_on)` |
| treasury_accounts | `(name, currency)` | `(company_id, name, currency)` |

Les contraintes déjà portées par un parent ou par un identifiant global (`(invoice_id, position)`,
`quote_id`, `payment_id`, `(source_type, source_id)`…) sont inchangées.

Ordre de la migration : colonne nullable, remplissage, passage à `NOT NULL`, index, puis clé
étrangère. MySQL peut refuser de modifier une colonne qui porte déjà une clé étrangère.

### 7. Numérotation des pièces : séquences par société

La table `document_sequences (company_id, type, year, last_number)` porte une contrainte unique
`(company_id, type, year)`. `App\Modules\Companies\Services\DocumentNumberer`, appelé via
`SequenceType::X->nextNumber($date)` :

- **refuse de s'exécuter hors transaction**. Si la transaction qui enregistre la pièce est annulée,
  le numéro est rendu avec elle, ce qui garantit l'absence de trous ;
- crée la ligne au besoin (`insertOrIgnore`), la verrouille (`lockForUpdate`) et l'incrémente.

L'année est celle de la date de la pièce, et la séquence **repart à 1 chaque année pour chaque
type**. Les types couverts sont : devis, factures et avoirs (préfixes paramétrables par la société),
règlements REG, écritures ECR, mouvements de trésorerie TRES, rapprochements RAP, dépenses DEP,
paiements fournisseurs PAI et immobilisations IMM.

Cette décision corrige aussi un défaut qui existait déjà en mono-société : un `id`
auto-incrémenté peut sauter après une transaction annulée.

**Reprise** : les numéros existants ne sont pas renumérotés. Chaque séquence reprend après le plus
grand numéro déjà émis pour ce type et cette année, quel que soit le préfixe. Par exemple, après
`FT-2026-00012`, la facture suivante sera `FT-2026-00013`.

### 8. Utilisateurs, rôles et administration de la plateforme

- **Un utilisateur appartient à une seule société** (`users.company_id`).
- **Le mode « teams » de Spatie n'est pas activé**, contrairement à la première version de cet ADR.
  Comme un utilisateur n'appartient qu'à une société, l'attribution d'un rôle à cet utilisateur vaut
  déjà pour sa seule société. La matrice de l'ADR 0001 est identique pour toutes les sociétés. Les 4
  rôles restent donc communs et sont créés une fois par `RolesAndPermissionsSeeder`, ou au premier
  provisionnement s'ils manquent. Il faudra activer « teams » si un utilisateur doit un jour
  appartenir à plusieurs sociétés ou si une société doit définir ses propres rôles.
- **Administrateur de la plateforme** : il est identifié par le booléen `users.is_platform_admin`,
  et non par un rôle Spatie, pour ne pas le mélanger aux permissions métier. Il n'a pas de société et
  n'accède qu'à `/platform/companies` : liste des sociétés, administrateurs, nombre d'utilisateurs,
  suspension et réactivation. Il n'a **aucun accès aux données comptables**. Il est créé en ligne de
  commande : `php artisan konta360:platform-admin <email>`.
- Une **société suspendue** (`companies.suspended_at`) voit ses utilisateurs déconnectés à la
  requête suivante et ne peut plus se connecter.

### 9. Inscription et provisionnement

La page `register` (Volt) demande la raison sociale, la devise, puis le nom, l'e-mail et le mot de
passe de l'administrateur. `App\Modules\Companies\Services\CompanyProvisioner::register()` crée,
**dans une seule transaction**, la société, son plan comptable et ses journaux, puis l'utilisateur
avec le rôle Administrateur.

`User` implémente désormais `MustVerifyEmail` : l'e-mail doit être vérifié avant tout accès aux
écrans protégés par `verified`.

Le plan comptable et les journaux sont décrits dans `CompanyProvisioner::ACCOUNTS` et `JOURNALS`.
C'est le même plan **provisoire** qu'auparavant, non validé comme plan SYSCOHADA officiel.
`provision()` est idempotent.

### 10. Vérification publique et fichiers

- `verification/{type}/{id}/{token}` : personne n'est connecté. Le document est chargé sans scope,
  puis sa société devient la société courante de la requête. **Le jeton reste inchangé**
  (HMAC du type et de l'`id`) : les `id` restent globaux, donc les QR codes déjà imprimés restent
  valides.
- Le logo de cette page est servi par `verification/{type}/{id}/{token}/logo` : seul le logo de la
  société émettrice d'un document vérifié est exposé.
- Les nouveaux fichiers de logo, signature et cachet sont rangés sous `companies/{id}/` sur le
  disque `public`. Les fichiers existants (`company/…`) restent en place, et leurs chemins sont
  conservés dans la société n°1.
- Le journal d'audit n'enregistre que les requêtes faites dans le contexte d'une société. Les actions
  de la plateforme n'y figurent pas.

## Validation requise

Ces choix ont été pris par défaut pour l'implémentation et restent à confirmer :

- **Utilisateur mono-société** : un cabinet comptable gérant plusieurs clients avec un seul compte
  n'est pas pris en charge (§ 8).
- **Numérotation** : remise à zéro chaque année pour chaque type. C'est à confirmer avec un
  expert-comptable, qui pourrait préférer une séquence continue.
- **Inscription libre**, avec seulement la vérification de l'e-mail. Il n'y a ni invitation ni
  validation manuelle. La plateforme peut suspendre une société après coup.
- **Hors périmètre**, à trancher avant l'ouverture au public : abonnement et facturation des
  clients, limites par offre, CGU et protection des données, export ou suppression des données
  d'une société.

## Conséquences

- **Tout nouveau modèle métier** doit utiliser `BelongsToCompany` et avoir sa colonne `company_id`.
  `ArchitectureTest` le vérifie.
- **Toute nouvelle règle de validation** `exists` ou `unique` sur une table métier passe par
  `CompanyRule`. `ArchitectureTest` le vérifie aussi.
- **Toute nouvelle pièce numérotée** passe par `SequenceType` et `DocumentNumberer`, dans une
  transaction. Il n'y a plus de numéro dérivé d'un `id`.
- **Code hors requête HTTP** (commande artisan, tinker, futur job de queue) : il faut fixer la
  société explicitement avec `app(CurrentCompany::class)->set($company)` ou `runAs()`. Sinon, toute
  requête métier lève `MissingCompanyContext`.
- **Tests** :
  - `Tests\TestCase` fixe la société n°1 comme contexte du corps de test, et chaque requête HTTP de
    test démarre **sans** société, comme en production ;
  - `UserFactory` rattache par défaut à la société n°1, `forCompany()` permet d'isoler, et
    `CompanyFactory` provisionne la société ;
  - `tests/Feature/Companies/CompanyIsolationTest.php` vérifie, pour chaque module, qu'un
    utilisateur de A ne peut ni lister, ni ouvrir (404), ni modifier (404), ni référencer dans un
    formulaire (erreur de validation) une donnée de B.
- **Migration irréversible** : les `down()` des migrations 2026_10_05 lèvent une exception. Il faut
  sauvegarder la base avant de migrer (voir `docs/deployment-cpanel.md`).
