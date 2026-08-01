# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Starboard is a Laravel 13 / PHP 8.3–8.5 app for tracking creators across social networks. Server-rendered Blade + Alpine.js + Tailwind, MySQL/MariaDB, PHPUnit. Every domain table is scoped to the authenticated user (multi-tenant).

## Commands

```bash
composer test        # Full gate: rector (dry-run) → peck → pint --test → phpstan → phpunit --coverage --min=85
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
- **Coverage gate is hard: `--min=85`.** New code without tests will fail the suite (and the Husky pre-commit hook, which runs `composer test`).
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
- Mass-assignment / serialization config is split: `User` uses the Laravel 13 attributes `#[Fillable([...])]` / `#[Hidden([...])]`, while `NetworkProfile`, `NetworkSource` and `NetworkTag` still declare `public $fillable` arrays. Rector's attribute rules only rewrite `protected $fillable` / `protected $hidden`, which is why the `public` ones were left as-is — don't reintroduce a *protected* property, it will be rewritten on the next `composer fix`.
- CSRF middleware is `Illuminate\Foundation\Http\Middleware\PreventRequestForgery` (Laravel 13 rename of `VerifyCsrfToken`, now also origin-checking via `Sec-Fetch-Site`). Feature tests that post without a token disable *that* class in `setUp()`.
- `config/cache.php` sets `serializable_classes => false`; caching PHP objects requires adding their classes to that allow-list.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3
- laravel/framework (LARAVEL) - v13
- laravel/mcp (MCP) - v0
- laravel/prompts (PROMPTS) - v0
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/breeze (BREEZE) - v2
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v12
- rector/rector (RECTOR) - v2
- alpinejs (ALPINEJS) - v3
- tailwindcss (TAILWINDCSS) - v3

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `vendor/bin/sail npm run build`, `vendor/bin/sail npm run dev`, or `vendor/bin/sail composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `vendor/bin/sail artisan route:list`). Use `vendor/bin/sail artisan list` to discover available commands and `vendor/bin/sail artisan [command] --help` to check parameters.
- Inspect routes with `vendor/bin/sail artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `vendor/bin/sail artisan config:show app.name`, `vendor/bin/sail artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `vendor/bin/sail artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `vendor/bin/sail artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== sail rules ===

# Laravel Sail

- This project runs inside Laravel Sail's Docker containers. You MUST execute all commands through Sail.
- Start services using `vendor/bin/sail up -d` and stop them with `vendor/bin/sail stop`.
- Open the application in the browser by running `vendor/bin/sail open`.
- Always prefix PHP, Artisan, Composer, and Node commands with `vendor/bin/sail`. Examples:
    - Run Artisan Commands: `vendor/bin/sail artisan migrate`
    - Install Composer packages: `vendor/bin/sail composer install`
    - Execute Node commands: `vendor/bin/sail npm run dev`
    - Execute PHP scripts: `vendor/bin/sail php [script]`
- View all available Sail commands by running `vendor/bin/sail` without arguments.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `vendor/bin/sail artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `vendor/bin/sail artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `vendor/bin/sail artisan list` and check their parameters with `vendor/bin/sail artisan [command] --help`.
- If you're creating a generic PHP class, use `vendor/bin/sail artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `vendor/bin/sail artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `vendor/bin/sail artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `vendor/bin/sail npm run build` or ask the user to run `vendor/bin/sail npm run dev` or `vendor/bin/sail composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/sail bin pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/sail bin pint --test --format agent`, simply run `vendor/bin/sail bin pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `vendor/bin/sail artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `vendor/bin/sail artisan test --compact`.
- To run all tests in a file: `vendor/bin/sail artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `vendor/bin/sail artisan test --compact --filter=testName` (recommended after making a change to a related file).

</laravel-boost-guidelines>
