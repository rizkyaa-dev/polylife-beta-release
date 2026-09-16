# AI execution and recovery

AI inference runs in a durable queue, not in a deferred HTTP callback. Local
development defaults to the database queue. Explicit AI connections must use
database, Redis, SQS or Beanstalkd (test environments may use fakes/sync).

Start the full local stack with `composer dev`. Alternatively run:

```console
php artisan serve
php artisan ai:work
php artisan schedule:work
```

These are separate processes. `ai:work` resolves the same connection and queue
names as the dispatcher; local mode uses queue:listen to reload PHP changes,
while production uses queue:work. `--once` processes at most one job.
Production still needs a supervised worker and scheduler. `php artisan serve`
alone serves HTTP, but intentionally does not execute long AI jobs.

Run the request-fingerprint migration when deploying. Restart workers after PHP
changes and rebuild frontend assets. Queue visibility/retry_after must exceed
the worker's 330-second timeout. The database queue defaults to 420 seconds;
configure other backends accordingly. Providers have bounded request timeouts,
and claimed runs receive an effort-specific turn deadline plus recovery grace.

## Retry contract

`PendingAiTurn` retains the request ID, original payload and accepted run ID.
Before acceptance is known, replay the identical payload and request ID. Once
accepted, retry only observes the existing run. Network errors, invalid replies,
HTTP 408/429/5xx and observation deadlines are not proof the operation failed.
Known runs are retained even if observation encounters an authentication error;
reauthenticate/reload rather than creating another operation.

The normal and edit composers share this recovery contract. An unresolved turn
cannot silently become a different prompt. Drafts stay in memory; this patch
does not persist chat content in browser storage. After known acceptance the
session URL is updated immediately, allowing reload to restore its active run.

AI requests with UUIDs bypass only the generic three-second duplicate-write
filter: durable, user-scoped idempotency handles replay. Authentication,
authorization, validation, rate limiting and signed workspace confirmation remain
active. Other workspace writes keep their duplicate filter.

Fingerprint validation binds prompt, original session parameter and edited
message ID. Legacy rows without fingerprints still enforce prompt and supplied
session matching. A busy conflict includes the owned active run ID, never another
user's run. A different turn's conflict must not be mistaken for acceptance of
the current prompt.

## Recovery safety

Completion locks the run and accepts results only while its status is running.
Claiming requires an unclaimed running run, so duplicate deliveries cannot start
a second execution. Failed/cancelled runs are never reopened; a new generation
uses a new run ID. This status fence rejects late results.

The scheduler expires stale leases; status polling also triggers recovery.
Expiry rechecks the current lease under lock to avoid invalidating an extension
based on a stale snapshot. Fatal process death may not invoke Laravel's failed
callback, so lease-based recovery is still necessary and not instantaneous.

Checks:

```console
php artisan test tests/Feature/Ai tests/Unit/Ai
node --test tests/Js/ai-turn-recovery.test.js
npm run build
```

The integration test verifies that HTTP enqueues without calling the provider,
then an explicit worker completes the same run. Provider responses in tests are
deterministic; no live-provider reliability or thousands-user capacity is claimed.
