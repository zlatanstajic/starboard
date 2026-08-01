# Deployment (cPanel)

This application uses Laravel's database queue for background work. The YouTube
fetch feature is guarded by separate execution and UI flags; both are disabled
by default and must remain disabled during the initial deployment.

## Prerequisites

- PHP 8.3 with the application-required extensions, including SimpleXML.
- A non-`array` session driver so fetch batch ownership survives requests.
- A supported queue connection and a worker capable of consuming the dedicated
  `youtube` queue.
- Outbound HTTPS access from the deployment host. Do not assume it works until
  the operator-only probe succeeds from production egress.

## Deploy code, schema, and assets

1. Back up the database.
2. Deploy the application with these rollout gates disabled:

   ```dotenv
   YOUTUBE_FETCH_ENABLED=false
   YOUTUBE_FETCH_UI_ENABLED=false
   YOUTUBE_FETCH_TRANSPORT=laravel-http
   YOUTUBE_FETCH_QUEUE=youtube
   ```

3. Install PHP dependencies without changing the lock file:

   ```bash
   composer install --no-dev --optimize-autoloader
   ```

4. Run migrations. In addition to Laravel's queue tables, this creates
   `youtube_fetch_batches`, `youtube_fetch_runs`, and
   `youtube_fetch_daily_budgets`:

   ```bash
   php artisan migrate --force
   ```

5. Build assets locally or in CI and deploy `public/build/`:

   ```bash
   npm ci
   npm run build
   ```

6. Rebuild configuration, route, and view caches, then restart workers:

   ```bash
   php artisan optimize:clear
   php artisan optimize
   php artisan queue:restart
   ```

## Queue worker

The YouTube worker must explicitly consume the configured queue. A cPanel cron
that drains it once per minute can use:

```cron
* * * * * /usr/local/bin/ea-php83 /home/USER/APP_PATH/artisan queue:work database --queue=youtube --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

For a persistent process manager, use the same `--queue=youtube` selection and
restart it after every deployment. The selected connection's `retry_after` must
always be greater than `YOUTUBE_FETCH_JOB_TIMEOUT`; the shipped database,
Beanstalkd, and Redis configuration enforces at least a 15-second margin.

Keep the ordinary application queue worker running separately if it handles
other queues.

## Rollout order

1. Back up the database and deploy migrations/code with both flags disabled.
2. Run the complete offline test suite and build assets.
3. Restart workers and verify the dedicated `youtube` queue is consumed.
4. From the production egress host, run the confirmed probe documented in
   `docs/YOUTUBE_FETCH_RUNBOOK.md` for one owned profile.
5. Enable execution only, clear config cache, restart workers, and fetch one
   canary profile through an operator-controlled path.
6. Verify audit rows, physical request accounting, cached channel-ID reuse, and
   unchanged `new_items` values on failures.
7. Run one small filtered batch.
8. Enable the UI flag, rebuild configuration cache, and monitor classified
   outcomes before increasing the batch limit.

The dashboard does not promise that every processed profile succeeded. It polls
an owned batch and reports terminal outcome counts. The control appears only
when both flags are enabled and is disabled while the shared cooldown is open or
the UTC daily request budget is exhausted.

## Rollback

1. Set `YOUTUBE_FETCH_ENABLED=false` and `YOUTUBE_FETCH_UI_ENABLED=false`.
2. Clear/rebuild configuration cache and restart queue workers. Every queued or
   released job re-checks the execution flag before reserving a request.
3. Pause the dedicated `youtube` worker and cancel active Laravel batches if
   necessary.
4. Revert transport selection independently. Only `laravel-http` is currently
   supported; unknown values fail closed.
5. Retain audit tables during an application rollback. If schema rollback is
   required, back up first and use the reversible migrations.

Rollback must not clear stored `youtube_channel_id` values or existing
`new_items` counts.

## Retention and pruning

Laravel batch rows and YouTube audit rows grow over time. Schedule Laravel batch
pruning and an operator-approved retention policy for terminal
`youtube_fetch_batches`/`youtube_fetch_runs`. Never prune active/retrying runs or
the current UTC budget row. Export audit data first when retention requirements
demand it. See the runbook for example read-only queries.

## Troubleshooting

| Symptom | Check |
|---|---|
| Control is absent | Both execution and UI flags, cached configuration, rebuilt assets. |
| Control is disabled | UTC budget row: `blocked_until` and `reserved_requests`. |
| Batch remains pending | Dedicated worker queue name, cron path/PHP binary, and `jobs` rows. |
| Counts do not change | Classified run outcome; malformed/blocked/error responses intentionally preserve the previous count. |
| Jobs fail repeatedly | `failed_jobs`, worker timeout, and `retry_after > job timeout`. |
| Polling loses a batch | Session persistence and matching `youtube_fetch_batches.user_id`. |
