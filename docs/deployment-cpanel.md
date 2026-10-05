# Déploiement sur cPanel sans Node.js

Le serveur de production ne compile pas les ressources frontend. Le dossier `public/build`
est donc généré localement avec Vite puis versionné dans Git.

## Avant chaque déploiement

Sur la machine de développement, uniquement si les fichiers CSS, JavaScript ou Blade ont changé :

```bash
npm install
npm run build
git add public/build
```

Le commit doit contenir :

- `public/build/manifest.json`
- les fichiers versionnés de `public/build/assets`

## Sur cPanel

Après la mise à jour du dépôt :

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
```

Le document root du domaine doit pointer vers le dossier `public` de l'application. Il ne faut
pas lancer `npm`, `vite` ou `npm run build` sur le serveur.

## Mise à jour vers le multi-sociétés (ADR 0002)

Les migrations `2026_10_05_*` transforment la base : la société actuelle devient la société n°1,
toutes les données et tous les utilisateurs existants lui sont rattachés, et la numérotation passe
aux séquences par société. **Ces migrations sont irréversibles.**

1. **Sauvegarder la base MySQL** (cPanel → Sauvegardes, ou `mysqldump`) et le dossier
   `storage/app/public` avant le déploiement.
2. Déployer comme d'habitude, avec `php artisan migrate --force`.
3. Créer le compte d'administration de la plateforme (adresse dédiée, sans société) :

   ```bash
   php artisan konta360:platform-admin support@votre-domaine.com
   ```

   Il donne accès à `/platform/companies` : liste des sociétés, suspension et réactivation.
4. Vérifier la configuration e-mail (`MAIL_*` dans `.env`) : les comptes créés par l'inscription
   doivent confirmer leur adresse avant d'accéder à l'application.

Les numéros déjà émis ne changent pas. Chaque séquence reprend après le plus grand numéro déjà
attribué dans l'année. Les QR codes déjà imprimés restent valides.

Une commande artisan ou une session `tinker` qui manipule des données métier doit d'abord fixer la
société :

```php
app(\App\Modules\Companies\Services\CurrentCompany::class)->set(\App\Models\Company::find(1));
```

## Abonnements, CGU et données (ADR 0003)

Après `php artisan migrate --force` :

- les sociétés déjà présentes passent en **accès offert** (formule Entreprise) : rien ne change pour
  elles tant que la plateforme ne retire pas cet accès ;
- renseigner dans `.env` les coordonnées affichées aux sociétés pour payer (`BILLING_MOBILE_MONEY`,
  `BILLING_BANK_ACCOUNT` et `BILLING_CONTACT_EMAIL`), puis lancer `php artisan optimize` ;
- vérifier les prix et les limites des formules dans `/platform/plans` ;
- vérifier et faire valider les textes `/legal/terms` et `/legal/privacy`. À chaque modification,
  augmenter `TERMS_VERSION` : les administrateurs seront invités à les accepter de nouveau.

Les paiements déclarés par les sociétés apparaissent dans `/platform/payments`. Ne confirmer qu'après
avoir retrouvé la référence sur le relevé.

Une société clôturée ne peut être supprimée qu'à l'issue de la durée légale de conservation :

```bash
php artisan konta360:purge-company <id>
```
