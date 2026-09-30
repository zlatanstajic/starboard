<laravel-boost-guidelines>
=== .ai/starboard rules ===

# Starboard Project Rules

## Commands: custom Docker runtime and local development tools

- This is not a Sail project. `docker-compose.yml` defines a custom `app` (PHP-FPM) / `nginx` / `mysql` stack with no `laravel.test` service, so `vendor/bin/sail` cannot work here even though the package is installed. Ignore any instruction to prefix commands with `vendor/bin/sail`.
- Run application Artisan commands inside the app container with `docker compose exec app php artisan <command>`. The Compose `app` image is production-only: it lacks Composer and development dependencies, so run `composer test` and other quality commands in a local PHP 8.5 development environment with Composer dependencies and a coverage driver. If container-owned `storage/framework/views` or `bootstrap/cache` blocks host-side Artisan or tests, fix permissions on those generated directories. A `tempnam()` warning from Blade compilation is a permissions problem, not an application bug.
- A cached `bootstrap/cache/config.php` written by the container also makes host-side env overrides silently ineffective, and clearing it needs write access to that container-owned directory.
- Command forms (run them in the local development environment): `composer test` is the full gate (rector dry-run, peck, pint --test, phpstan, phpunit with `--coverage --min=85`); `composer fix` auto-fixes rector plus pint before committing; individual steps are `composer test:pint`, `composer test:phpstan`, `composer test:rector`, `composer test:peck`, `composer test:phpunit`. `composer serve` runs server, queue listener, pail and vite together.

## The test suite runs on two different database drivers

- Local runs hit a real MySQL database (`starboard_testing`) and require a local, ignored `.env.testing`; `composer test:phpunit` aborts without it. CI runs the identical suite on SQLite.
- Any raw SQL or migration behaviour that differs between the two drivers passes locally and fails on push. Escape LIKE wildcards with `!` and never with a backslash: MySQL treats a backslash as LIKE's default escape character, SQLite has no default escape character at all.
- CI runs on pushes to `master` and `issues/*`, and on pull requests targeting `master`. Branch names are kebab-case (`issues/12-short-description`), never snake_case.

## UserObserver is deliberately disabled under tests

- `AppServiceProvider::boot()` registers `UserObserver` only when `! app()->runningUnitTests()`. In production a newly created `User` is seeded with every `NetworkSourcesEnum` source plus starter tags and profiles; in tests `User::factory()` yields a user with none of them.
- Create the sources, tags and profiles a test needs explicitly. Never assume a factory-created user matches production state, and never "fix" a failing test by enabling the observer.

## YouTube fetching is a bounded subsystem with a spend guard

- `App\Contracts\YouTube\YouTubeTransport` is bound in `AppServiceProvider::register()` to `LaravelHttpYouTubeTransport` and fails closed: any `youtube.transport` value other than `laravel-http` throws `YouTubeDisabledException`. The transport speaks data transfer objects (`YouTubeFetchRequest`, `YouTubeFetchResult`, `YouTubeProfileResult`), not arrays.
- `YouTubeRequestBudget` enforces a per-UTC-day request reservation cap and a shared circuit breaker across `youtube_fetch_daily_budgets`, `youtube_fetch_runs` and `youtube_fetch_batches`, using `lockForUpdate()` inside a retrying transaction. Execution is gated by `YOUTUBE_FETCH_ENABLED` (server) and `YOUTUBE_FETCH_UI_ENABLED` (dashboard); all knobs live in `config/youtube.php`.
- Live outbound API access from the CLI requires `php artisan youtube:probe --profile=<id> --confirm-live` and refuses without the flag. Read `docs/YOUTUBE_FETCH_RUNBOOK.md` before touching budget or circuit rows. Recorded HTTP fixtures live in `tests/fixtures/youtube/`.

## Architecture

- Layering is Controller to Service to Repository to Model. Controllers never touch Eloquent directly, and every read of a user-owned model goes through the owner-scoping helpers on `App\Repositories\Repository`.
- The full set of architectural invariants (multi-tenant `UserScope`, filter-list hash lifecycle, public list page sort narrowing, Blade component prop contracts) is documented at the top of `CLAUDE.md`. Read that section before changing repositories, query filters or the public list pages.

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

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

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

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

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

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

</laravel-boost-guidelines>
