# Guide utilisateur - Audit SGS

Ce guide decrit le parcours utilisateur de base et les comportements attendus de l'application.

## 1) Acces a l'application

URL locale: `http://127.0.0.1:8000/login`

![Ecran de connexion](./screenshots/login-page.png)

Notes sur la capture:
- Zone 1: champ **Matricule** (identifiant utilisateur).
- Zone 2: champ **Mot de passe**.
- Zone 3: bouton **Se connecter** pour ouvrir une session.
- Si les identifiants sont invalides, rester sur cette page et verifier le message d'erreur.

## 2) Protection des routes metier

Les pages metier sont protegees par authentification. Sans session, la navigation vers `/import`, `/consultation`, `/audit`, `/archive`, `/users` redirige vers `/login`.

![Redirection vers login depuis import](./screenshots/import-redirect-login.png)

Notes sur la capture:
- L'URL demandee est une route metier (`/import`).
- L'application redirige automatiquement vers la page **Connexion**.
- Ce comportement est normal et confirme la protection middleware (`auth`).

## 3) Parcours apres connexion (a valider avec un compte actif)

Apres connexion reussie, verifier ce flux:
1. Ouvrir `Import` et charger un fichier conforme.
2. Ouvrir `Consultation` et verifier l'affichage d'une table.
3. Ouvrir `Audit` et executer une comparaison.
4. Ouvrir `Archive` pour verifier la liste des imports.
5. Si profil super admin, ouvrir `Users`.

## 4) Checklist de verification rapide

- [ ] `/login` repond en `200`.
- [ ] `/` redirige vers `/login` si non connecte.
- [ ] `/import` redirige vers `/login` si non connecte.
- [ ] `/consultation` redirige vers `/login` si non connecte.
- [ ] `/audit` redirige vers `/login` si non connecte.
- [ ] `/archive` redirige vers `/login` si non connecte.
- [ ] `/users` redirige vers `/login` si non connecte.

## 5) Pre-requis techniques (local)

- XAMPP demarre (Apache + MySQL).
- Fichier `.env` configure vers la bonne base locale.
- Lancement serveur Laravel: `php artisan serve --host=127.0.0.1 --port=8000`.
