---
name: cachet-tests
description: Lancer, filtrer et diagnostiquer la suite de tests Pest du projet Cachet. À utiliser pour « lance les tests », « vérifie que ça passe », après une modification de code, ou quand un test échoue.
---

# Tests Pest — Cachet

## Pré-requis (vérifier d'abord)
```bash
command -v php && php -v | head -1     # PHP 8.3+ attendu (CI : 8.4)
test -d vendor || echo "vendor manquant → composer install"
test -f .env  || cp .env.example .env
grep -q '^APP_KEY=$' .env && php artisan key:generate
```
Si `php` est introuvable : **s'arrêter et le dire à l'utilisateur**. Ne jamais affirmer que des tests passent sans les avoir exécutés.

## Lancer
```bash
composer test                                  # suite complète (php artisan test)
php artisan test --filter='serves the status'  # par nom de test (texte du it())
php artisan test tests/Feature/SmokeTest.php   # un fichier
php artisan test --stop-on-failure
```
`phpunit.xml` force : SQLite `:memory:`, cache/session `array`, queue `sync`, mail `array`. Seule la suite `tests/Feature` existe.

## Échecs fréquents
- **404/500 sur `/` ou `/dashboard`, erreurs d'assets Filament/Vite** → assets non publiés :
  ```bash
  php artisan vendor:publish --tag=laravel-assets --ansi --force
  php artisan vendor:publish --tag=cachet-assets --ansi --force
  php artisan filament:assets --ansi
  ```
- **Config en cache** → `php artisan optimize:clear`.
- **Table absente** → les migrations de `cachethq/core` tournent via `RefreshDatabase`/`LazilyRefreshDatabase` ; vérifier `tests/Pest.php` / `TestCase.php`.
- **Différence 8.3 vs 8.4** → la CI est en 8.4 ; signaler toute syntaxe ou dépréciation spécifique à une version.

## Rapport
Donner la commande exacte, le nombre de tests passés/échoués et la sortie d'erreur pertinente (pas un résumé optimiste).
