# Deployment (cPanel)

This guide covers deploying the application to cPanel shared hosting, with
emphasis on the background **Fetch** feature (YouTube "new items" check), which
relies on queued jobs and therefore requires a cron-driven queue worker.

## Overview

The Fetch button dispatches a **batch of queued jobs** (`QUEUE_CONNECTION=database`).
Jobs are only inserted into the database — a worker process must consume them.
On shared cPanel you cannot run a persistent `queue:work` daemon, so a **cron
job** drives the worker instead.

Without a running worker, clicking Fetch queues jobs that never execute and the
button spins on "Fetching… 0/N" indefinitely.

## Prerequisites

- PHP **8.3**, **8.4** or **8.5** (match the app's target; cPanel often exposes
  these as `ea-php83` / `ea-php84` / `ea-php85`).
- MySQL database with the `jobs`, `job_batches`, and `failed_jobs` tables
  (created by the default migration) plus the `network_profiles.new_items`
  column migration.
- `SESSION_DRIVER` set to a persistent driver (`database`, `file`, or `cookie`)
  — **not** `array`. The fetch batch id is stored in the session and read back
  by the polling endpoint.
- Outbound HTTPS (port 443) to `youtube.com` allowed from the server.

Resolve the correct PHP binary and app path once, over SSH, and reuse them below:

```bash
which php          # or: which ea-php83  -> e.g. /usr/local/bin/ea-php83
pwd                # your app root, e.g. /home/USER/APP_PATH
```

## 1. Deploy the code and assets

1. Upload / pull the application code to the app root.
2. Install PHP dependencies:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
3. Build front-end assets. cPanel usually has no Node, so **build locally or in
   CI and upload** the generated `public/build/` directory:
   ```bash
   npm ci && npm run build
   ```
   The Fetch spinner and disabled-button styles only exist in a fresh build.

## 2. Environment and migrations

1. Ensure `.env` on production has at least:
   ```
   APP_ENV=production
   APP_DEBUG=false
   QUEUE_CONNECTION=database
   SESSION_DRIVER=database
   ```
2. Run migrations (adds `new_items` and the queue/batch tables if missing):
   ```bash
   php artisan migrate --force
   ```

## 3. Rebuild caches

The new `network-profiles.fetch.status` route must be present in the route
cache, so always rebuild caches after a deploy:

```bash
php artisan config:clear && php artisan route:clear && php artisan view:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

## 4. Cron job for the queue worker (required)

Add the cron entry in cPanel → **Cron Jobs**. Choose **one** of the two
approaches below. Replace the PHP binary path and app path with your own.

### Option A — Direct worker cron (simplest)

Runs the worker every minute; it drains the queue and exits.

```
* * * * * /usr/local/bin/ea-php83 /home/USER/APP_PATH/artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

- `--stop-when-empty` — the worker exits once the queue is empty.
- `--max-time=55` — hard stop before the next minute so runs never overlap/stack.

### Option B — Scheduler cron (recommended if you have other periodic tasks)

A single system cron drives Laravel's scheduler; the queue worker (and any
future scheduled task) is declared in `routes/console.php`.

System cron entry:

```
* * * * * /usr/local/bin/ea-php83 /home/USER/APP_PATH/artisan schedule:run >> /dev/null 2>&1
```

Schedule definition in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('queue:work --stop-when-empty --max-time=55')
    ->everyMinute()
    ->withoutOverlapping();
```

`schedule:run` reads `routes/console.php` each minute and runs whatever is due.
`routes/console.php` never runs on its own — the cron is what triggers it.

> Use Option A **or** Option B, not both. Both require a per-minute system cron.

## 5. Verify

Over SSH:

```bash
# Outbound access to YouTube works
curl -I https://youtube.com/@youtube/videos

# Manually drain the queue once and watch it process
php artisan queue:work --stop-when-empty

# Inspect queue state
php artisan queue:failed
```

In the app: click **Fetch**, confirm the "Fetch started" alert, the button
shows "Fetching… X/Y", and on completion a "Fetch complete — N new items found"
alert appears after the page reloads.

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| Button stuck on "Fetching… 0/N" forever | No queue worker running — cron missing or wrong PHP binary/app path. |
| Fetch completes but counts never change | Outbound HTTPS to `youtube.com` blocked, or YouTube returned a non-OK response (check `storage/logs/laravel.log` for `FetchYouTubeNewItemsJob` warnings). |
| "Fetch complete" alert never shows / no progress updates | `SESSION_DRIVER=array`, or the `fetch.status` route isn't cached (rerun step 3). |
| Spinner/disabled button styling missing | `public/build/` not rebuilt/uploaded (step 1.3). |
| Jobs pile up in `failed_jobs` | Worker PHP version mismatch, or `job_batches` table missing (rerun step 2). |
