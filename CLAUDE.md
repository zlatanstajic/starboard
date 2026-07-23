# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Starboard is a Laravel 12 / PHP 8.3 app for tracking creators across social networks. Server-rendered Blade + Alpine.js + Tailwind, MySQL/MariaDB, PHPUnit. Every domain table is scoped to the authenticated user (multi-tenant).

## Commands

```bash
composer test        # Full gate: rector (dry-run) → peck → pint --test → phpstan → phpunit --coverage --min=95
composer fix         # Auto-fix: rector process + pint (run before committing)
composer serve       # Runs server + queue listener + pail logs + vite concurrently
composer setup       # Fresh install: deps, key, migrate:fresh --seed, ide-helper, assets, test
```

Run individual gate steps: `composer test:pint`, `composer test:phpstan`, `composer test:rector`, `composer test:peck`, `composer test:phpunit`.

Run a single test / subset:
```bash
php artisan test --filter=NetworkProfileControllerTest
php artisan test tests/Unit/Services/NetworkProfileServiceTest.php
```

- **Tests hit a real MySQL database, not SQLite.** They require a `starboard_testing` MySQL DB and a committed `.env.testing`; `composer test:phpunit` aborts if `.env.testing` is missing. The SQLite `:memory:` block in `phpunit.xml` is intentionally commented out.
- **Coverage gate is hard: `--min=95`.** New code without tests will fail the suite (and the Husky pre-commit hook, which runs `composer test`).
- Use `composer backup` (`snapshot:create`) / `spatie/laravel-db-snapshots` for DB snapshots.

## Architecture

**Layering: Controller → Service → Repository → Model.** Controllers never touch Eloquent directly; they call a Service, which calls a Repository. Services are constructor-injected (`NetworkProfileService(NetworkProfileRepository)`).

**Three abstract base classes define shared behavior — extend these, don't bypass them:**

- `App\Http\Controllers\Controller` — provides `handleView()`, `handleRedirect()`, `handleException()` (routes errors through SweetAlert), and a `$pageName` default for redirects.
- `App\Repositories\Repository` — provides `buildStandardQuery()`, which wraps Spatie QueryBuilder. It reads the model's `ALLOWED_INCLUDES`, `ALLOWED_FILTERS`, `ALLOWED_SORTS` constants and auto-converts `*_id` / `id` string filters to `AllowedFilter::exact` (avoids partial-match leaks on foreign keys). Per-repository extras (scopes, callbacks) are merged in via a private `additionalAllowedFilters()`.
- `App\Models\Model` — declares the three `ALLOWED_*` array constants (empty by default) and human-readable date accessors (`createdAtShort`, etc.).

**To make a model filterable/sortable/includable in listings**, populate its `ALLOWED_FILTERS` / `ALLOWED_SORTS` / `ALLOWED_INCLUDES` constants and/or add `#[Scope]` methods on the model, then register scope/callback filters in the repository's `additionalAllowedFilters()`. Query params follow Spatie conventions (`?filter[...]`, `?sort=`, `?include=`).

**Multi-tenancy via `UserScope` global scope** (`app/Models/Scopes/UserScope.php`): domain models add it in `booted()`, constraining every query to `Auth::id()`. When a repository sub-query only needs pivot/existence checks across users (e.g. tag filters), it calls `->withoutGlobalScopes()` on the *inner* query — the outer query keeps its UserScope, so no cross-user data leaks. Preserve this pattern when editing repository filters.

**Enums centralize magic strings** (`app/Enums/`): `DatabaseTableNamesEnum`, `CacheNamesEnum`, `NetworkSourcesEnum`. Reference these instead of hardcoding table/cache names.

**Soft deletes + restore-on-create:** `NetworkProfile` uses `SoftDeletes`. The repository's `create()` restores a matching trashed record instead of inserting a duplicate; DB unique-constraint violations (SQLState `23000`) are caught and rethrown as domain exceptions (`app/Exceptions/<Domain>/*`).

**YouTube "new items" is a batched queue flow:** `NetworkProfileService::fetchNewItems()` builds one `FetchYouTubeNewItemsJob` per YouTube-video profile, staggered by `FETCH_STAGGER_SECONDS` to avoid bot-like traffic, and dispatches them as a named `Bus::batch`. The `fetch` route is `throttle:6,1`; `fetch/status` polls batch progress. The job resolves a channel id then reads YouTube's Atom feed (`feeds/videos.xml`) rather than scraping HTML.

**Domain models:** `NetworkProfile` (a tracked account) belongs to a `User` and a `NetworkSource` (the platform, whose `url` holds a `{username}`/`{id}`/`{hash}`/`{uuid}` placeholder expanded by `profileUrl()`), and many-to-many with `NetworkTag`.

**i18n:** English + Serbian; locale switched via `/locale/{locale}` route + session, applied by `SetLocale` middleware. User-facing controller strings come from `lang/` (`__('messages...')`).

## Conventions

- `declare(strict_types=1)` is enforced by Pint; use `===`/`!==` (strict comparison rule is on).
- Pint uses the Laravel preset with custom rules (see `pint.json`) including enforced class-element ordering — run `composer fix` rather than hand-formatting.
- PHPStan (larastan) runs at level 5 over `app/`.
- Rector runs Laravel + PHP upgrade sets; `composer test` fails on any pending Rector change.
- `peck` spell-checks code; add legitimate domain words to `peck.json`'s ignore list.
- `_ide_helper*.php` / `.phpstorm.meta.php` are generated (`composer artisan:ide-helper`) — don't hand-edit.
