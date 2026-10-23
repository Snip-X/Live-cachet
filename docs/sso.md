# Authentification unique (SSO OpenID Connect)

Le dashboard (`/dashboard`) propose un bouton **« Sign in with SSO »** (libellé configurable) qui authentifie via n'importe quel fournisseur OpenID Connect. Le login e-mail / mot de passe reste disponible comme **accès de secours**, mais uniquement depuis des adresses IP autorisées.

La page de statut publique n'est pas concernée.

## Installation

```bash
composer require socialiteproviders/openidconnect
php artisan migrate        # ajoute la colonne users.oidc_sub
```

## Côté fournisseur d'identité

Créer une application « web » / client confidentiel OIDC :

| Paramètre | Valeur |
|---|---|
| Flux | Authorization Code (PKCE accepté) |
| Redirect URI | `https://<votre-domaine>/auth/oidc/callback` |
| Scopes | `openid email profile` (+ ce qu'il faut pour les groupes) |

## Configuration (`.env`)

```dotenv
SSO_ENABLED=true

# Fournisseur générique : URL de l'émetteur (sans /.well-known/openid-configuration)
OIDC_BASE_URL=https://idp.example.com/realms/acme
OIDC_CLIENT_ID=cachet
OIDC_CLIENT_SECRET=********
```

Raccourcis optionnels via `OIDC_PROVIDER`, qui déduisent l'URL et gèrent les particularités de l'IdP :

| `OIDC_PROVIDER` | Variables |
|---|---|
| `entra` (Microsoft Entra ID) | `OIDC_TENANT=<id ou domaine du tenant>` |
| `keycloak` | `OIDC_SERVER_URL=https://id.example.com`, `OIDC_REALM=acme` |
| `okta` | `OIDC_DOMAIN=acme.okta.com` |
| `auth0` | `OIDC_DOMAIN=acme.eu.auth0.com` |
| `google` | — |

`OIDC_REDIRECT_URI` n'est à renseigner que si l'URL publique diffère de `APP_URL` (reverse proxy) ; par défaut c'est l'URL de la route `sso.callback`.

## Qui peut se connecter

Les comptes sont **créés automatiquement** à la première connexion SSO (non admin par défaut).

**Une politique d'accès est obligatoire** : si ni `SSO_ALLOWED_DOMAINS` ni `SSO_ALLOWED_GROUPS` n'est renseigné, toute connexion SSO est refusée, sauf `SSO_ALLOW_ANY_USER=true` (déconseillé : tout utilisateur non admin peut modifier incidents, composants et métriques).

| Variable | Effet |
|---|---|
| `SSO_ALLOWED_DOMAINS` | Domaines e-mail acceptés (ex. `example.com,example.fr`). |
| `SSO_ALLOWED_GROUPS` | Groupes IdP acceptés ; un seul suffit. |
| `SSO_ALLOW_ANY_USER` | `true` pour accepter tout utilisateur de l'IdP sans domaine ni groupe. |
| `SSO_ADMIN_GROUPS` | Groupes donnant le rôle admin. Le flag admin est alors **resynchronisé à chaque connexion SSO**. Si le claim de groupes est absent du token, rien n'est modifié ; le dernier admin n'est jamais rétrogradé. Sans cette variable, l'admin se gère dans Cachet. |
| `SSO_GROUPS_CLAIM` | Claim contenant les groupes (défaut `groups`). Notation pointée pour un claim imbriqué (`realm_access.roles`), nom complet pour un claim namespacé (`https://example.com/groups`). |
| `SSO_TRUST_IDP_EMAIL` | Voir ci-dessous. |

### Comptes existants

Un compte Cachet existant avec le même e-mail est **lié** à l'identité SSO uniquement si l'IdP certifie l'e-mail (`email_verified: true`). Sinon la connexion est refusée : la liaison donnerait le compte (éventuellement admin) à quiconque contrôle cet e-mail chez l'IdP.

Certains IdP n'envoient jamais `email_verified` (Microsoft Entra ID notamment). Si votre IdP ne délivre que des adresses de votre organisation, mettez `SSO_TRUST_IDP_EMAIL=true`, **toujours avec `SSO_ALLOWED_DOMAINS`** limité à vos domaines.

Spécificités des groupes :
- **Entra ID** : activer « Groups claim » dans la configuration du token ; les groupes arrivent sous forme d'**Object ID** (GUID) à mettre dans les variables. Au-delà de 200 groupes, Entra n'émet plus le claim (overage) : utiliser plutôt des groupes assignés à l'application.
- **Keycloak** : ajouter un mapper « Group Membership » (claim `groups`, *full group path* désactivé) au client scope.
- **Google** : pas de claim de groupes ; filtrer par `SSO_ALLOWED_DOMAINS`.

## Accès de secours par mot de passe

```dotenv
SSO_PASSWORD_LOGIN_ALLOWED_IPS=10.10.0.0/16,192.0.2.10
```

- SSO activé + liste vide → login mot de passe **refusé partout**.
- SSO désactivé + liste vide → aucune restriction (comportement d'origine).
- Hors liste, le formulaire n'est pas affiché et toute tentative est refusée côté serveur.

> ⚠️ **Reverse proxy** : l'IP utilisée est celle de `$request->ip()`.
> - Sans `CACHET_TRUSTED_PROXIES`, derrière un proxy, **toutes les requêtes ont l'IP du proxy** (souvent `127.0.0.1`) : n'autorisez jamais cette IP dans ce cas, sinon tout Internet a accès au formulaire.
> - Renseignez `CACHET_TRUSTED_PROXIES` avec l'IP **exacte** du proxy. Avec `*`, un client qui atteint l'application sans passer par le proxy peut falsifier `X-Forwarded-For` et contourner la restriction.

Gardez au moins un compte admin local (créé avec `php artisan cachet:make:user`) pour le cas où l'IdP serait indisponible.

## Limites connues

- La MFA de Cachet (application TOTP) ne s'applique qu'au login par mot de passe ; pour le SSO, la MFA est celle de l'IdP.
- La déconnexion est locale à Cachet (pas de déconnexion de l'IdP).
- Un seul fournisseur OIDC à la fois.
