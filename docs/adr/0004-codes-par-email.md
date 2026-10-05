# ADR 0004 — Confirmation par code à 8 chiffres envoyé par e-mail

- **Statut** : Accepté et implémenté
- **Date** : 2026-10-07

## Contexte

Depuis l'ADR 0002, n'importe qui peut créer une société. Deux risques en découlent :

- des comptes créés avec une adresse e-mail qui n'appartient pas à la personne ;
- l'accès à une comptabilité avec un simple mot de passe volé ou réutilisé.

La vérification d'e-mail de Laravel envoyait un lien signé. Elle ne protégeait que l'inscription,
pas la connexion.

## Décision

1. **Inscription** : un code à 8 chiffres est envoyé à l'adresse saisie. Pour
   cela, `User::sendEmailVerificationNotification()` est surchargée. Tant que le
   code n'est pas saisi sur `/verify-email`, le middleware `verified` bloque
   l'application. L'ancien lien signé (route `verification.verify` et son
   contrôleur) est supprimé.
2. **Connexion** : un mot de passe correct ne connecte plus l'utilisateur.
   - `LoginForm::authenticate()` vérifie les identifiants sans ouvrir de
     session.
   - La connexion en attente est gardée en session côté serveur (`PendingLogin`)
     pendant 10 minutes.
   - Un code est envoyé. L'utilisateur n'est connecté (`Auth::login`, puis
     régénération de la session) qu'après la saisie de ce code sur
     `/login/code`.
   - L'état du compte et de la société est revérifié à ce moment-là.
   - Recevoir le code prouve aussi la possession de l'adresse : un compte non
     vérifié devient vérifié.
3. **Règles des codes** (`AuthenticationCodeService`) :
   - 8 chiffres tirés par `random_int`, valables 10 minutes et utilisables une
     seule fois ;
   - 5 essais au maximum par code ;
   - un nouveau code au plus toutes les 60 secondes, et seul le dernier code
     envoyé reste valable ;
   - un code de connexion ne sert pas à vérifier l'adresse, et inversement
     (`AuthenticationCodePurpose`) ;
   - la base ne stocke qu'un HMAC-SHA256 du code, avec la clé de
     l'application ;
   - les espaces sont acceptés, le code étant affiché « 1234 5678 » dans
     l'e-mail.
4. **Envoi** : synchrone, car l'hébergement n'a pas de file d'attente.
   L'expéditeur est `MAIL_FROM_ADDRESS`, soit **dev@fintchweb.com**.
5. **Hors périmètre** : la connexion par la plateforme ne fait pas exception,
   ses administrateurs reçoivent aussi un code. Il n'y a pas d'« appareil de
   confiance » qui dispenserait du code : « Rester connecté » prolonge
   seulement la session une fois connecté.

## Conséquences

- **Le service n'est utilisable qu'avec un envoi d'e-mails qui fonctionne.**
  Si le SMTP est mal configuré, plus personne ne peut se connecter.
- En développement, `MAIL_MAILER=log` écrit les e-mails, donc les codes, dans
  `storage/logs/laravel.log`.
- Les tests récupèrent le code dans l'e-mail intercepté (`Mail::fake()`). Les
  tests qui utilisent `actingAs()` ne sont pas concernés.
