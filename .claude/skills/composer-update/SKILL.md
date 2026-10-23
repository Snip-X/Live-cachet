---
name: composer-update
description: Procédure sûre pour mettre à jour les dépendances Composer de Cachet (laravel/framework, cachethq/core, bumps Dependabot, composer.lock) et vérifier que l'application fonctionne encore. À utiliser pour « mets à jour », « bump », « update composer », conflit de composer.lock ou PR Dependabot.
---

# Mise à jour des dépendances

## 1. Préparer
```bash
git status --short                 # partir d'un arbre propre
php -v | head -1                   # la contrainte est php ^8.3 ; la CI teste en 8.4
composer outdated --direct
```

## 2. Mettre à jour (le plus ciblé possible)
```bash
composer update laravel/framework --with-all-dependencies   # un paquet + ses deps
composer update cachethq/core                                # suit dev-main : lire le changelog/commits upstream
composer update                                              # tout (éviter sans demande explicite)
```
Ne pas modifier les contraintes de `composer.json` (ex. passage de version majeure, `php` requis) sans accord de l'utilisateur.

## 3. Republier les assets (obligatoire)
Les `post-update-cmd` le font normalement ; à refaire si les scripts ont été sautés (`--no-scripts`) :
```bash
php artisan vendor:publish --tag=laravel-assets --ansi --force
php artisan vendor:publish --tag=cachet-assets --ansi --force
php artisan filament:assets --ansi
php artisan migrate        # le core peut apporter de nouvelles migrations
php artisan optimize:clear
```

## 4. Vérifier
- `composer test` (voir skill `cachet-tests`).
- Lister ce qui a changé : `git diff composer.lock | grep -E '"name"|"version"'` et le résumer (paquet : ancienne → nouvelle version).
- Pour un saut de version majeure Laravel, consulter le guide d'upgrade officiel et vérifier `bootstrap/app.php`, `config/*.php`.

## 5. Commit
Uniquement si l'utilisateur le demande. Message dans le style du dépôt : `Update Composer lockfile` ou `Bump <paquet> ...`.
