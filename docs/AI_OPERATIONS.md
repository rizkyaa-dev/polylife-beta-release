# AI runtime operations

The AI runtime is intentionally kept inside the Laravel application, while slow
inference is isolated behind dedicated queues. Production must use Redis for the
queue, cache, locks, rate limits, circuit breaker, and provider concurrency slots.

## Workspace navigation

The AI mode entry (`/workspace/ai`, with no `session` query parameter) opens the
fresh composer, just like the existing `new=1` link. It does not implicitly
select the most recently updated conversation or create a session. History
links explicitly select an owned session using `?session=<id>`. After a chat
submission is accepted, the client writes that session into the current URL,
so reloads keep the selected conversation and can resume its active run.
Leaving AI mode and returning through the mode switch opens the fresh composer;
saved conversations remain available in history.

The AI UI palette lives in `resources/css/ai/theme.css`; component styles are
imported through `ai-workspace.css`. Keep role-based colors in that palette and
run `node --test tests/Js/ai-theme-contrast.test.js` after palette changes.
The existing per-user theme preference remains authoritative. See the
[UI rework report](ai-ui-rework-2026-10-01.md) for component ownership and
validation limits.

## Production configuration

```dotenv
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
AI_QUEUE_CONNECTION=redis
AI_FAST_QUEUE=ai-fast
AI_HEAVY_QUEUE=ai-heavy
AI_COMPUTE_QUEUE=ai-compute
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
php artisan queue:work redis --queue=ai-compute --sleep=1 --tries=1 --timeout=30 --max-time=3600
php artisan queue:work redis --queue=default --sleep=1 --tries=1 --timeout=90 --max-time=3600
```

The compute consumer processes science receipts and server computation. It is
required when science delegation is enabled, including browser computation:
client receipts must be settled before the original chat run resumes. Custom
queue names must match `AI_COMPUTE_QUEUE`. Local `ai:work` consumes all three AI queues.
Queue reservation/visibility must exceed 510 seconds; database, Redis and
Beanstalkd configurations enforce a minimum of 540 seconds. Set SQS visibility
to at least 540 seconds separately.

Run multiple fast/heavy/compute workers under Supervisor, systemd, or the platform's
process manager. Restart workers after every deployment with
`php artisan queue:restart`.

The job remains single-attempt deliberately. Only retryable rate-limit and
unavailable responses are replayed by `ResilientLlmClient` within the original
deadline. Ambiguous connection failures/timeouts are never automatically replayed.
A structurally invalid coding artifact receives at most one bounded repair within
that same deadline. Confirmed domain mutations retain their separate transaction.

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

Token counters are recorded per inference attempt, including coding repairs,
science planning, continuation and failed turns. `complete` means every observed
attempt supplied a complete, valid measurement, `partial` displays a lower bound, and `unknown` displays
no measured total. Legacy zero counters are not evidence of zero provider usage.
Valid usage is retained even when content is refused or rejected. Partial reports
contribute their known counters without counting as a complete measurement;
malformed counters do not become measured zero. Decorators observe each attempt
once, including retries.
Session completeness also checks completed assistant messages without a matching
run in that session. These legacy answers keep an otherwise measured session
`partial`; entirely unmeasured sessions remain `unknown`. No historical usage
is synthesized or estimated by the summary. Composer, resumed runs and message
edits update counters from the same server status callback, including failed and
cancelled outcomes. Gemini reasoning uses `thoughtsTokenCount`; reported totals
already include thoughts, while partial fallback totals add them once.
These counters are telemetry, not an invoice reconciliation system.

The composer pill displays accumulated session input/output from the server,
both after reload and during run observation. The popover shows the last
submission separately, with a text-only draft estimate while typing. Draft
estimates never replace recorded usage, and repeated status updates replace
session totals rather than adding them again. Legacy usage coverage still
determines whether the accumulated session counters are complete or partial.

`AI_CONTEXT_TOKEN_BUDGET` limits assembled conversation history using the existing
estimate of three Unicode characters per token. Archived tool results and digest
headers share this budget. Tool facts use at most 20% of it, capped at 12,000
characters; oversized facts are omitted as whole JSON records. Instructions,
tool declarations, the new prompt and subsequent tool results are outside this
history budget; it is not an exact provider context-window calculation.

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
