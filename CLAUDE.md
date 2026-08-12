# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Starboard is a Laravel 13 / PHP 8.5 app for tracking creators across social networks. Server-rendered Blade + Alpine.js + Tailwind, MySQL/MariaDB, PHPUnit. Every domain table is scoped to the authenticated user (multi-tenant).

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
- `App\Repositories\Repository` — provides `buildStandardQuery()`, which wraps Spatie QueryBuilder and accepts optional subject and request instances for saved-query replay. It reads the model's `ALLOWED_INCLUDES`, `ALLOWED_FILTERS`, `ALLOWED_SORTS` constants and auto-converts `*_id` / `id` string filters to `AllowedFilter::exact` (avoids partial-match leaks on foreign keys). Per-repository extras (scopes, callbacks) are merged in via a private `additionalAllowedFilters()`. Every standalone public read of a user-owned model must start with `ownerScopedQuery()`; public relationship/subquery builders must pass through `constrainToOwner()`. Both remove `UserScope` and immediately restore an explicit `user_id` predicate; never perform those two operations separately.
- `App\Models\Model` — declares the three `ALLOWED_*` array constants (empty by default) and human-readable date accessors (`createdAtShort`, etc.).

**To make a model filterable/sortable/includable in listings**, populate its `ALLOWED_FILTERS` / `ALLOWED_SORTS` / `ALLOWED_INCLUDES` constants and/or add `#[Scope]` methods on the model, then register scope/callback filters in the repository's `additionalAllowedFilters()`. Query params follow Spatie conventions (`?filter[...]`, `?sort=`, `?include=`).

**Multi-tenancy via `UserScope` global scope** (`app/Models/Scopes/UserScope.php`): domain models add it in `booted()`, constraining every query to `Auth::id()`. When a repository sub-query only needs pivot/existence checks across users (e.g. tag filters), it calls `->withoutGlobalScopes()` on the *inner* query — the outer query keeps its UserScope, so no cross-user data leaks. Preserve this pattern when editing repository filters.

**Enums centralize magic strings** (`app/Enums/`): `DatabaseTableNamesEnum`, `CacheNamesEnum`, `NetworkSourcesEnum`. Reference these instead of hardcoding table/cache names.

**Soft deletes + restore-on-create:** `NetworkProfile` uses `SoftDeletes`. The repository's `create()` restores a matching trashed record instead of inserting a duplicate; DB unique-constraint violations (SQLState `23000`) are caught and rethrown as domain exceptions (`app/Exceptions/<Domain>/*`).

**YouTube "new items" is a batched queue flow:** `NetworkProfileService::fetchNewItems()` builds one `FetchYouTubeNewItemsJob` per YouTube-video profile, staggered by `FETCH_STAGGER_SECONDS`, and dispatches them as a named `Bus::batch`. The `fetch` route is `throttle:6,1`; `fetch/status` polls batch progress. The job uses a server-only YouTube Data API v3 key to resolve the channel's uploads playlist with `channels.list`, then pages `playlistItems.list`. API calls happen outside the final source/profile locking transaction; the channel ID and completed count are persisted atomically only if the profile snapshot remains valid.

**Domain models:** `NetworkProfile` (a tracked account) belongs to a `User` and a `NetworkSource` (the platform, whose `url` holds a `{username}`/`{id}`/`{hash}`/`{uuid}` placeholder expanded by `profileUrl()`), and many-to-many with `NetworkTag`. `FilterList` belongs to a `User` and stores a named dashboard capture in the `filter_lists` table (unguessable `hash`, JSON `filters`, publish state/timestamp, description, timestamps, and soft deletion). A capture is created unpublished unless the save modal's `Published` checkbox is ticked; an unpublished capture still gets a hash at insert because `filter_lists.hash` is NOT NULL unique. Only the navigation link is labelled "Filters" (`messages.filter_list.page_name`); every other domain label, identifier, route and key keeps the `filter_list` / `filter-lists` naming.

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
- The dashboard's network-source filter is `x-network-source-filter`, a Blade + Alpine dropdown rather than a `<select>`, because `<option>` cannot hold the brand SVG that `x-source-icon` renders. Don't "simplify" it into a Tom Select instance: `resources/css/app.css` carries ~10 global `.ts-control` / `.ts-dropdown` rules shared with the tag filters, so a single-select consumer would force edits to CSS the tag filters depend on. Its trigger deliberately repeats the sibling native selects' `text-sm` + `p-2.5` + border classes to hold the filter row at one 42px height.
- Dashboard filter rows are hand-balanced flex rows (`md:w-1/3` + 4×`md:w-1/6` on row 1; `md:w-1/4` + 2×`md:w-1/6` + `md:flex-1` on row 2). The fractions are tuned for the `lg`+ width where the container caps at `max-w-7xl`; adding a control to a row means re-checking the narrow `md` end too, where the `md:flex-1` search box absorbs all the slack.
- The dashboard filter-capture action is `Save` (`data-save-filter-list`), dispatching `open-save-list-modal` into `x-save-modal`. The post-save modal reads the `saved_filter_list` flash key and deliberately renders `x-copy-link` for both published and unpublished outcomes; the UI rename does not change domain naming such as `filter_list`, `filter-lists`, the table, or its columns. Modal Cancel buttons that combine `dark:text-white` with `hover:bg-gray-200` must also use `hover:text-black`, or keep the hovered dark surface with a `dark:hover:bg-*` class as `x-delete-modal` does.
- The Filter Lists table has no `url` column: the `name` cell *is* the public link (`publicUrl()` in both `href` and `title`, opened with `target="_blank" rel="noopener noreferrer"`). `name` stays the locked column-visibility entry, so the link can never be hidden away.
- The Filter Lists table's `filters` column (right of `description`) renders the saved capture through `FilterListService::describeFilters()`, which returns a `list<array{label, value}>` built from the *same* `lang/` strings the dashboard dropdowns use — so a new dashboard filter needs a matching arm in that service's `filterLabel()`/`filterValue()` match blocks or it will render its raw key and value. `network_source_id` and `tags`/`exclude_tags` are resolved to names from id => name maps the controller passes in (`getAllForOwner()` on the source/tag services), which keeps the lookup owner-scoped: an id belonging to another user falls back to the bare id rather than leaking a name. The controller pre-builds one `describedFilters[$id]` entry per row so the Blade loop stays free of service resolution.
- Each Filter Lists row carries an Apply action (`data-apply-filter-list`, leftmost in the `actions` cell, a plain `<a>` because the target is an idempotent GET that should stay middle-clickable). Applying a capture is **not** a server-side replay: `FilterListService::buildDashboardUrl()` expands the saved capture into an explicit `/dashboard?filter[...]&sort=...` query string via `route('dashboard', $query)`, so the dashboard's existing dropdowns, search box, tag multi-selects, save button and clear button all keep working unchanged against it — they read the very same `request('filter.*')` keys. That URL-building shares one private allow-list gate (`allowedCapture()`) with `sanitizeFilters()`, so the write path and the read path can never diverge: a stored key since renamed or removed, or a stale `sort`, is dropped from the generated URL instead of being emitted and rejected with a 400 by Spatie. An empty capture yields the bare `route('dashboard')`. `route()` encodes array values as indexed `filter%5Btags%5D%5B0%5D=` parameters, which Spatie and the Blade layer both read back as repeated parameters — don't "tidy" them into a comma-joined scalar, since `tags` is strict AND over the id list and a joined value collapses to a single tag. The apply path deliberately does **not** inherit `NetworkProfileRepository::buildSavedFilterListQuery()`'s public-page narrowing: no `is_public` constraint is added, so the owner's private profiles stay visible and the dashboard result set may legitimately differ from the public list page for the same list. The dashboard's own `getAll()` still applies its long-standing implicit `exclude_from_dashboard = false` unless the capture pins `filter.network_source_id`; that is dashboard behaviour, not something the apply path adds. The controller pre-builds an `applyUrls[$id]` map next to `describedFilters[$id]`, keeping both the Blade loop free of service resolution and the URL out of a `FilterList` accessor that would otherwise need to know repository-level allow-lists.
- `x-listing-filters` is the shared filter panel for the *simple* listings (`/network-sources`, `/network-tags`, `/filter-lists`). It takes `:sort-options`, `:search-placeholder`, `:columns`, optional `:status-options`, `status-name` (default `exclude_from_dashboard`) and `:show-profiles-filter` (default `true`). The defaults preserve the source/tag pages; Filter Lists binds status to `filter[is_published]` and hides profile-count filtering.
- Component prop contracts to preserve: `x-column-visibility-control` requires `:columns` — a list of `['key' => string, 'label' => string, 'locked' => bool]`, where the locked entry renders `checked disabled` (the hide-all guard, always `name`); `x-table-header` takes `filters-storage-key` (default `show_filters`) and `:show-create-button` (default `true`); `x-filter-select` takes `:navigate` (default `true`) and must be given `:navigate="false"` when it lives inside a form instead of self-navigating on change; `x-filter-toggle-button` requires `storage-key` (no default) and is the single source of the filter toggle's appearance, shared by `x-table-header` (which passes its own `filtersStorageKey`) and `shared-list.blade.php` — it emits `data-filter-toggle` for tests to locate it without matching class strings, flips the surrounding Alpine scope's `showFilters` and writes `'1'`/`'0'` to the given key, and merges extra classes through the attribute bag, so callers append layout-only utilities (the public header's `shrink-0`) rather than restating the appearance.
- Per-page client state lives under per-page `localStorage` keys: `<page>_columns` (`dashboard_columns`, `network_sources_columns`, `network_tags_columns`, `filter_lists_columns`, whose Filter Lists entry now includes a `filters` key) and `show_filters_<page>` (`show_filters_filter_lists` for Filter Lists; the dashboard keeps the bare `show_filters`; the public list page uses `show_filters_shared_list` — one key for every `/lists/{hash}`, not one per hash, because the panel's open/closed state is a visitor preference rather than a property of the list). Clearing filters is a plain navigation to `request()->url()` and never writes `localStorage`, which is what keeps the column selection intact across a clear.
- Public list URLs are `/lists/{hash}` and nothing else: there is no user-facing slug, the `/lists/{token}` route is constrained to `[A-Za-z0-9]+`, and `FilterListService::getPublicList()` treats the whole token as the hash. `FilterList::publicUrl()` builds that URL through `route('filter-lists.show', …)` rather than a hardcoded path, so the prefix lives in `routes/web.php` alone. `filter_lists.hash` is the table's only unique index and is soft-delete-inclusive, so a hash is permanently burned after use — which is why a `23000` violation on a `filter_lists` write can only mean a minted hash was taken and is rethrown as `FilterListHashGenerationException`. `FilterListRepository::upsert()` mints a hash at insert. `setPublished()` carries a fresh hash only when `FilterListService::republish()` supplies one, and `publish()` delegates to `republish()` only for a list whose `published_at` is already non-null; first publication of a list created unpublished therefore reuses its create-time hash instead of burning it. Both hash-writing paths run through the private `translatingHashCollision()` wrapper, so a collision surfaces as the domain exception instead of escaping as a raw `QueryException` 500; route any new hash-writing method through it too. The public list page is the only indexable page behind a per-list URL, so `PublicLayout` (`layouts/public.blade.php`) takes `title`/`description`/`canonical`/`noindex` props and renders the full SEO head: `<title>` suffixed with the app name + `messages.filter_list.public.lists` ("Lists"), meta description (falling back to `messages.filter_list.public.default_description`, `Str::limit`ed to 160 chars — the limit is before the `...` Str::limit appends), canonical (defaults to `url()->current()`; `shared-list` passes `publicUrl()` so query filters never fork the canonical), robots, Open Graph and Twitter card tags. The social image is `public/logo.png` (800x800, so the Twitter card is `summary` rather than `summary_large_image`, which wants 2:1) and the tab icon pairs `favicon.ico` with `logo.png` for `shortcut icon`/`apple-touch-icon`, matching the app/guest layouts. The `og:image:width`/`height` literals are pinned to the shipped file by a test, so replacing the logo at another size fails the suite instead of silently lying to crawlers. `x-list-icon` is the generic inline list glyph (not `x-source-icon`, which only renders `NetworkSourcesEnum` brand logos) and heads the public list title. The landing page (`HomeController::index()` rendering `welcome`) shows the newest `FilterListService::PUBLIC_HIGHLIGHT_LIMIT` (10) published lists via `FilterListRepository::getLatestPublished()` — the one cross-user read of the table, deliberately `withoutGlobalScope(UserScope::class)` because visitors are unauthenticated; it stays safe by exposing only already-public rows (`is_published`, soft-delete-excluded) and never accepts request filters. The public page's own filter dropdowns are built from `NetworkProfileRepository::getSourcesForFilterList()` / `getTagsForFilterList()`, **not** the `getAllForOwner()` pair the authed Filter Lists page uses: both derive their options from `buildSavedFilterListQuery()` — the single private definition of "what the list exposes" that `getForFilterList()`'s first pass also uses — so a visitor only ever sees sources and tags actually present on the profiles the list publishes. Feeding those dropdowns from the owner's full source/tag collection instead leaks the names of private profiles' sources and tags to anyone holding the link; keep any new public dropdown derived from that shared builder. The public sort dropdown is deliberately narrower than the dashboard's: it reads `messages.filter_list.public.sort` (name/username, both directions) and the second pass registers only `publicAllowedSorts()` with `includeModelSorts: false`, so a visitor addressing any dropped sort (`?sort=-number_of_visits`) gets a 400 rather than a working ordering. `name` is an `AllowedSort::callback` on `coalesce(title, username)` — matching what the Name column actually displays, since `title` is nullable — and is *not* in `NetworkProfile::ALLOWED_SORTS`. The owner's saved sort is still honoured as the default: it is looked up among the public sorts and, when absent, falls back to a plain `AllowedSort::field()`. That fallback instance is handed to `defaultSort()` **only** and must never be appended to the list passed to `allowedSorts()` — registering it would make the saved sort addressable (`?sort=-number_of_visits` on any list whose owner saved it), defeating the narrow-public-sort guarantee; a test asserts both directions of a saved non-public sort still 400 while the listing keeps that saved default order. It must be handed to `defaultSort()` as the resolved `AllowedSort` instance with `defaultDirection()`, never as a string — `defaultSorts()` converts a string via `AllowedSort::field()`, which bypasses the registered callback and would emit `order by name` against a non-existent column. Public profile replay is deliberately two-pass on the same Eloquent builder: saved owner filters first, then the reduced visitor filter set. The saved sort is supplied through `defaultSort()` on the second pass, so a visitor `sort` takes precedence without appending a competing owner `ORDER BY`. The public tags filter is `filterPublicTags()`, which intersects requested ids with the list's exposed tag set (so a tag the list does not publish cannot be used as a boolean oracle) — but the `any`/`none` sentinels that `shared-list.blade.php` also offers as dropdown options are short-circuited to `filterTags()` *before* the id normalization: they name no tag, and running them through the intersection leaves them as the literal strings `['any']`/`['none']`, which match no numeric id and silently return zero profiles. Keep any future sentinel on the same short-circuit. The exposed id set is memoized per `FilterList` id on the repository instance (`$exposedTagIds`), because the filter callback would otherwise replay `buildSavedFilterListQuery()` a third time on every filtered, unauthenticated request. The public page's filter form is collapsed behind `x-filter-toggle-button` exactly like the authenticated listings: the `<section>` owns the `showFilters` Alpine state and restores it from `show_filters_shared_list` in `x-init`, its `<header>` is a `sm:flex-row … sm:justify-between` row holding the title block (`<div class="min-w-0">` around the `<h1>` and the description) on the left and the right-aligned toggle on the right, and the form sits in an `x-cloak x-show="showFilters" x-transition` wrapper carrying the `mb-6` that used to be the results wrapper's `mt-6` — so a collapsed panel leaves no empty gap above the table. The panel stays collapsed on first paint even when the URL already carries `filter[...]`/`sort`; the incoming query is not sniffed to auto-expand it.
- The column dropdown overhangs its card, so the listing cards use `overflow-visible` (**not** the `overflow-hidden` the dashboard card still carries) and the panel is `absolute z-[60]` inside a `relative z-40` actions row. Without all three the dropdown is clipped or painted under the table whenever the listing is short enough that the panel extends past the card — `z-index` alone cannot escape an `overflow-hidden` ancestor.
- Repository `filterSearch()` methods take `string|array $value`: Spatie explodes a comma-bearing filter value into an array, and a `string`-only signature under `declare(strict_types=1)` raises a `TypeError` (an `Error`, so `handleException()`'s `catch (Exception)` misses it and the request 500s). They delegate to `Repository::applySearchFilter($query, $columns, $value)`, which joins an exploded value back together, groups the OR conditions in one closure (so the OR cannot escape `UserScope`), and matches with `orWhereRaw("… like ? escape '!'")` — wildcards are escaped with `!`, not `\`, because MySQL treats a backslash as LIKE's default escape character but **SQLite has none** (and CI runs the suite on SQLite, so backslash escaping passes locally and fails there). Route new search filters through that helper instead of hand-writing `where(… 'like' …)`.
- `NetworkSourceRepository::getAll()` / `NetworkTagRepository::getAll()` register their Spatie filters and sorts only when called with `filterable: true` (the listing controllers do; the dashboard, which reuses both to fill its source/tag dropdowns, does not). The dashboard request carries network-*profile* filters and sorts, and registering them on these models would make Spatie reject the dropdown queries. Keep new listing filters behind that flag.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
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
