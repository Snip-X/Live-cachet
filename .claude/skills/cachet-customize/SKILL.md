---
name: cachet-customize
description: Méthode pour modifier ou étendre le comportement de Cachet (page de statut, dashboard Filament, API, utilisateurs, webhooks, langues, thème) sans toucher à vendor/cachethq/core. À utiliser dès qu'une demande porte sur une fonctionnalité de Cachet plutôt que sur du Laravel générique.
---

# Personnaliser Cachet sans forker le core

Ce dépôt est une coquille ; le code de Cachet est dans `vendor/cachethq/core` (`dev-main`). Toute modification dans `vendor/` est perdue au prochain `composer install`.

## 1. Localiser le comportement
Déléguer à l'agent `cachet-core-explorer` (ou grep dans `vendor/cachethq/core/src`) pour trouver la classe, la config ou la vue concernée et ses points d'extension.

## 2. Choisir le levier, du plus léger au plus lourd
1. **Variable d'env / `config/cachet.php`** : `CACHET_PATH`, `CACHET_DOMAIN`, `CACHET_GUARD`, `CACHET_USER_MODEL`, `CACHET_TITLE`, `CACHET_TRUSTED_PROXIES`, `CACHET_API_RATE_LIMIT`, `CACHET_BEACON`, `CACHET_DEMO_MODE`, webhooks (`CACHET_WEBHOOK_QUEUE_*`), `supported_locales`, `middleware` / `api_middleware`. Toujours ajouter la nouvelle variable dans `.env.example`.
2. **`AppServiceProvider`** : rebind d'une classe du core dans le container (`$this->app->bind(CoreClass::class, AppClass::class)`), rate limiters, macros, `Gate::define`/policies, listeners d'événements Cachet (`Event::listen`).
3. **Modèle utilisateur** : `app/Models/User.php` (référencé par `cachet.user_model`).
4. **Routes** : `routes/web.php` (attention : la page de statut occupe `/` par défaut via `cachet.path`).
5. **Vues** : `php artisan vendor:publish --tag=cachet` puis éditer uniquement les vues publiées nécessaires (supprimer celles non modifiées pour suivre les mises à jour du core).
6. **Dernier recours** : signaler à l'utilisateur que le besoin nécessite une contribution upstream à `cachethq/core`.

## 3. Vérifier
- Nouveau fichier PHP → en-tête de licence Alt Three.
- Ajouter un test Pest dans `tests/Feature` (agent `pest-test-writer`), lancer via le skill `cachet-tests`.
- `php artisan optimize:clear` si config/routes/vues semblent ignorées.
