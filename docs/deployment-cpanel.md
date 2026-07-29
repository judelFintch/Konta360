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
