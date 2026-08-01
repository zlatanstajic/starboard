# YouTube Fetch Runbook

## Production-egress probe record

Status: **PENDING — NOT RUN**

No automated test or development action has contacted YouTube. The probe must
be run by an operator from the production egress host after offline verification
and before enabling execution:

```bash
php artisan youtube:probe --profile=PROFILE_ID --confirm-live
```

The profile must belong to a real application user. Record the date, host,
release identifier, profile ID, outcome, HTTP status, effective transport,
duration, and physical request count here after the production run. Do not paste
response bodies, cookies, tokens, or redirect URLs.

If Laravel HTTP cannot obtain both a normal channel page and a valid Atom feed
while an approved real browser can, stop rollout. Adding a browser-grade adapter
or system dependency requires separate approval and a pinned deployment plan.

## Configuration and defaults

| Variable | Default | Purpose |
|---|---:|---|
| `YOUTUBE_FETCH_ENABLED` | `false` | Server-side execution gate checked by controller, service, and job. |
| `YOUTUBE_FETCH_UI_ENABLED` | `false` | Dashboard visibility gate. |
| `YOUTUBE_FETCH_TRANSPORT` | `laravel-http` | Only supported transport; other values fail closed. |
| `YOUTUBE_FETCH_CONNECT_TIMEOUT` | `5` | Connection timeout in seconds. |
| `YOUTUBE_FETCH_REQUEST_TIMEOUT` | `15` | Per-request timeout in seconds. |
| `YOUTUBE_FETCH_JOB_TIMEOUT` | `45` | Worker job timeout. Queue `retry_after` must be greater. |
| `YOUTUBE_FETCH_MAX_REDIRECTS` | `2` | Maximum manually guarded redirects. |
| `YOUTUBE_FETCH_MAX_RESPONSE_BYTES` | `2097152` | Maximum accepted response body. |
| `YOUTUBE_FETCH_RETRY_ATTEMPTS` | `3` | Domain retries scheduled only by the job. |
| `YOUTUBE_FETCH_RETRY_BASE_SECONDS` | `15` | Exponential backoff base. |
| `YOUTUBE_FETCH_RETRY_FACTOR` | `2` | Backoff multiplier. |
| `YOUTUBE_FETCH_RETRY_CEILING_SECONDS` | `900` | Maximum calculated delay. |
| `YOUTUBE_FETCH_MAX_RETRY_AFTER` | `900` | Maximum honored `Retry-After`. |
| `YOUTUBE_FETCH_BATCH_LIMIT` | `50` | Maximum filtered profiles per batch. |
| `YOUTUBE_FETCH_STAGGER_SECONDS` | `5` | Delay between initially queued jobs. |
| `YOUTUBE_FETCH_DAILY_REQUEST_LIMIT` | `500` | UTC physical request reservations per day. |
| `YOUTUBE_FETCH_BLOCKED_COOLDOWN_SECONDS` | `3600` | Shared cooldown after consent/sign-in walls. |
| `YOUTUBE_FETCH_QUEUE` | `youtube` | Dedicated queue name. |
| `YOUTUBE_FETCH_LOG_CHANNEL` | `stack` | Structured terminal/retry log channel. |

The Laravel HTTP adapter uses installed framework dependencies, disables
automatic redirects/retries, and sends no static consent cookie.

## Outcome actions

| Outcome | Operator action |
|---|---|
| `success` | No action; verify counts only when investigating a report. |
| `invalid_url`, `unsafe_redirect` | Correct the source template/profile; investigate attempted SSRF. |
| `consent_required`, `sign_in_required` | Leave cooldown in place; verify production egress and probe before retrying. |
| `rate_limited` | Reduce batch size/frequency; honor the recorded retry delay. |
| `transient_http_failure`, `transport_failure` | Inspect network/upstream health; bounded job retry is expected. |
| `permanent_http_failure` | Correct profile/source or investigate upstream policy; no automatic retry. |
| `channel_id_missing` | Verify the channel page still contains a stable channel ID. |
| `malformed_feed` | Preserve count; inspect safe signals/status, never store/log the body. |
| `request_budget_exhausted` | Wait for the next UTC day or approve a deliberate reset. |
| `shared_circuit_open` | Wait until `blocked_until`; investigate the blocking outcome. |
| `stale_profile` | Expected after concurrent profile/source edits; dispatch again if needed. |
| `unexpected_failure` | Inspect sanitized error and `failed_jobs`; fix before retrying broadly. |

## Budget and circuit procedures

Inspect without mutation:

```sql
SELECT budget_date, reserved_requests, blocked_until, block_reason
FROM youtube_fetch_daily_budgets
ORDER BY budget_date DESC
LIMIT 7;
```

Normal recovery is automatic: the budget moves to a new UTC row and a circuit
closes when `blocked_until` passes. For an operator-approved emergency circuit
reset, first disable execution and stop the YouTube worker, back up the row, then:

```sql
UPDATE youtube_fetch_daily_budgets
SET blocked_until = NULL, block_reason = NULL
WHERE budget_date = UTC_DATE();
```

Do not decrement `reserved_requests` for timeouts, crashes, redirects, or failed
connections: reservation occurs before transmission and intentionally remains
charged. Resetting the request count requires incident approval and evidence
that reservations were erroneous.

## Safe audit and log queries

Recent runs (no response content is stored):

```sql
SELECT uuid, youtube_fetch_batch_id, network_profile_id, stage, outcome,
       http_status, transport, request_count, retry_count, duration_ms,
       retry_delay_seconds, finished_at
FROM youtube_fetch_runs
ORDER BY id DESC
LIMIT 100;
```

Outcome rates:

```sql
SELECT outcome, COUNT(*) AS total
FROM youtube_fetch_runs
WHERE created_at >= UTC_TIMESTAMP() - INTERVAL 24 HOUR
GROUP BY outcome
ORDER BY total DESC;
```

Active batches:

```sql
SELECT id, user_id, laravel_batch_id, state, total, started_at
FROM youtube_fetch_batches
WHERE state IN ('preparing', 'active')
ORDER BY started_at;
```

Application logs should contain run/batch/profile identifiers, stage, outcome,
status, transport, duration, request/retry counts, and delay. They must never
contain bodies, cookies, tokens, credentials, or full redirect URLs.

## Queue troubleshooting

1. Confirm configuration: `php artisan config:show youtube`.
2. Confirm the worker consumes the configured dedicated queue.
3. Verify queue `retry_after` is greater than the configured job timeout.
4. Inspect `jobs`, `job_batches`, and `failed_jobs`; do not blindly retry all
   failed jobs while execution is enabled.
5. Restart workers after config/code changes: `php artisan queue:restart`.
6. Confirm session persistence and batch ownership if polling is inactive.
7. Keep execution disabled while correcting repeated blocked or unsafe outcomes.

## Monitoring and retention

Alert on elevated blocked, rate-limited, malformed-feed, transport-failure, and
unexpected-failure rates; budget usage approaching the configured limit; active
batches older than the expected maximum; and queue depth/oldest-job age.

Prune Laravel batches with the framework scheduler according to operational
requirements. Define retention for terminal YouTube batches/runs (for example,
90 days) only after audit/compliance approval. Delete child runs before parent
batches when required by the database, retain active/retrying records, and never
delete the current UTC budget row during active execution.
