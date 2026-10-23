# Cachet 3.x — application shell

Cachet est une status page auto-hébergée. **Ce dépôt n'est que la coquille applicative Laravel** : quasiment toute la logique métier vit dans le package `cachethq/core` (`dev-main`), installé dans `vendor/cachethq/core` :
modèles (Component, Incident, Schedule, Metric…), migrations métier, ressources Filament du dashboard, page publique, API, seeders de démo.

Ne jamais modifier `vendor/`. Les points d'extension côté app sont :
- `config/cachet.php` (path, domain, guard, user_model, middleware, rate limit API, webhooks, locales, demo_mode…) — variables `CACHET_*` dans `.env`
- `app/Providers/AppServiceProvider.php` (bindings, rate limiters, overrides)
- `routes/web.php`, `routes/console.php`, `routes/middleware.php`
- vues/assets publiés (`php artisan vendor:publish --tag=cachet`)
- `app/Models/User.php` (modèle utilisateur utilisé par Cachet via `cachet.user_model`)

## Stack
- PHP `^8.3` (composer.json) — **la CI et `.agents/setup` tournent en 8.4** : le code doit rester compatible 8.3 ET 8.4.
- Laravel 13, Sanctum 4, Nightwatch, Filament (via core), Pest + pest-plugin-laravel.
- Base par défaut : SQLite (`database/database.sqlite`). Tests : SQLite `:memory:`.
- Branche principale : `3.x`.

## Commandes
```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate
php artisan db:seed --class='Cachet\Database\Seeders\DatabaseSeeder'   # données de démo (test@test.com / test123)

composer test                       # = php artisan test (Pest, tests/Feature uniquement)
php artisan test --filter=nom       # un test ciblé
composer dev                        # serve + queue + pail + vite
```

Après **tout** `composer update` / bump de dépendance, republier les assets sinon l'app et les tests cassent :
```bash
php artisan vendor:publish --tag=laravel-assets --ansi --force
php artisan vendor:publish --tag=cachet-assets --ansi --force
php artisan filament:assets --ansi
```

## Conventions
- Chaque fichier PHP commence par l'en-tête de licence Alt Three (copier à l'identique depuis un fichier existant, ex. `app/Models/User.php`).
- Style Laravel Pint (preset laravel), appliqué automatiquement par la CI sur push vers `3.x`. `laravel/pint` n'est pas dans `require-dev` : utiliser `composer global require laravel/pint` puis `pint`, ou ne pas s'en soucier (la CI corrige).
- Tests Pest au style de `tests/Feature/SmokeTest.php` : `it('...', function () { ... })`, `$this->get()`, `User::forceCreate([... 'is_admin' => true])` pour un admin.
- Typage strict des retours (`: void`, etc.), docblocks courts comme dans le code existant.

## SSO (OpenID Connect)
Voir `docs/sso.md`. Code : `app/Auth/` (résolution des comptes, garde IP du login mot de passe), `app/Http/Controllers/Auth/SsoController.php`, `app/Filament/Pages/Auth/Login.php` (remplace la page de login du panel `cachet`, branchée dans `AppServiceProvider::registerLoginPage()`, qui l'enregistre aussi auprès de Livewire). Config : `config/sso.php` + `services.openidconnect` (package `socialiteproviders/openidconnect`).

## Environnement local
- `.agents/` et `.amp/` appartiennent à Amp (setup d'orbe, services) : ne pas les modifier ni exécuter `.agents/setup` (utilise sudo/apt).
- Si `php` / `composer` sont absents du PATH ou `vendor/` manquant, le signaler plutôt que d'affirmer que du code a été testé.
