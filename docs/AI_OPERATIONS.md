# AI runtime operations

The AI runtime is intentionally kept inside the Laravel application, while slow
inference is isolated behind dedicated queues. Production must use Redis for the
queue, cache, locks, rate limits, circuit breaker, and provider concurrency slots.

## Production configuration

```dotenv
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
AI_QUEUE_CONNECTION=redis
AI_FAST_QUEUE=ai-fast
AI_HEAVY_QUEUE=ai-heavy
AI_MAX_ACTIVE_RUNS_PER_USER=2
AI_MAX_ACTIVE_RUNS_GLOBAL=100
AI_PROVIDER_MAX_CONCURRENCY=20
AI_PROVIDER_RETRIES=1
AI_CIRCUIT_FAILURE_THRESHOLD=5
AI_CIRCUIT_COOLDOWN_SECONDS=30
AI_REDISPATCH_AFTER_SECONDS=120
AI_CONTEXT_TOKEN_BUDGET=24000
AI_SENSITIVE_DATA_RETENTION_DAYS=30
```

Tune `AI_PROVIDER_MAX_CONCURRENCY` below the provider account quota. The global
active-run limit is a backlog guard, not a throughput target.

## Worker layout

Run the scheduler as a persistent supervised process. It expires stale leases,
redelivers orphaned jobs, and prunes old sensitive payloads.

```shell
php artisan schedule:work
php artisan queue:work redis --queue=ai-fast --sleep=1 --tries=1 --timeout=510 --max-time=3600
php artisan queue:work redis --queue=ai-heavy --sleep=1 --tries=1 --timeout=510 --max-time=3600
php artisan queue:work redis --queue=default --sleep=1 --tries=1 --timeout=90 --max-time=3600
```

Run multiple fast/heavy workers under Supervisor, systemd, or the platform's
process manager. Restart workers after every deployment with
`php artisan queue:restart`.

The job remains single-attempt deliberately. Retryable provider transport errors
are retried inside `ResilientLlmClient`, before any confirmed domain mutation.

## Capacity planning

Required provider concurrency is approximately:

```text
incoming prompts per second × average provider duration in seconds
```

Ten prompts/second with an eight-second average therefore needs roughly 80
concurrent provider calls. The configured provider semaphore, provider quota,
budget, and worker count must all support that number before increasing it.

Monitor at least:

- queue wait and queue depth per queue;
- provider p50/p95 latency and retry/error rate;
- run duration, stale-run count, and dispatch attempts;
- active runs per user and globally;
- provider token/cost data when exposed by the selected API.

Provider success logs are sampled through `AI_PROVIDER_SUCCESS_LOG_SAMPLE`;
failures are always logged.

## Load test

`tests/Load/ai-chat.js` performs authenticated end-to-end chat requests and polls
the resulting run. It calls the configured real AI provider and can incur cost.
Use a dedicated test account and environment.

```shell
k6 run -e BASE_URL=http://127.0.0.1:8000 -e EMAIL=load@example.test -e PASSWORD=secret tests/Load/ai-chat.js
```

For concurrent virtual users, pre-create isolated accounts and use a template such
as `EMAIL_TEMPLATE=load+{vu}@example.test`. A single account is intentionally
limited to two queued/running requests and is unsuitable for concurrency testing.

Increase `K6_VUS` and `K6_DURATION` gradually. A release should not increase load
until enqueue p95, queue wait, provider quota, database connections, and error rate
remain within the deployment's SLO.
