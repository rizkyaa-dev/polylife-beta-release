# Eco activation and strict mode selection

## Cause and repaired contract

Selecting Eco previously admitted a run even when its schema was missing or the
feature was disabled. Missing policy then selected the Default agent prompt/context.
The reported run had exactly one model call, 12,820 input tokens, 46 output tokens,
and no Eco context metadata. The local database lacked all four Eco columns.

An accepted Eco request now guarantees a persisted Eco mode and valid frozen policy.
New requests are rejected before any conversation writes when Eco is disabled or its
schema is incomplete. Send/edit return HTTP 422 with `code: "eco_unavailable"`; the
existing frontend treats this as a definitive rejection and displays its message.
An invalid persisted policy fails the run with the same error code before inference.
Legacy fallback runs cannot be replayed as successful Eco acceptances. Valid admitted
Eco runs still complete under their frozen policy after the feature is disabled.

Default retains its existing payload, fingerprint, prompt, context assembly, tools,
and response contract. Its admission does not probe Eco schema.

## Scope

- `AiConversationBranchService`: check Eco readiness before transaction/admission,
  freeze a valid policy, and validate existing Eco request replays.
- `AiChatController`: expose a definitive, sanitized rejection for send and edit.
- `AiAgentOrchestrator`: fail missing/unsupported Eco policy instead of using Default.
- `AiEcoUnavailableException` and `AiRunErrorPresenter`: share the user-facing message.
- Existing Eco/migration tests now assert strict selection instead of legacy fallback;
  the new `AiEcoModeReadinessTest` covers send, edit, frozen policy, and retry boundaries.

## Verification on 2026-10-11

| Check | Status | Evidence |
| --- | --- | --- |
| Regression before fix | PASS | Five new tests failed for silent acceptance/Default execution; the valid Eco path passed. |
| Regression after fix | PASS | All six readiness tests passed, 47 assertions. |
| Eco/migration/policy suite | PASS | 30 passed, 172 assertions on disposable SQLite. |
| Full PHP suite | PASS | 675 passed, 5,688 assertions; two Vite-development tests skipped without a hot-file fixture. |
| JavaScript suite | PASS | 168 passed. |
| Production asset build | PASS | `npm.cmd run build` succeeded in the workspace. |
| Changed PHP formatting / whitespace | PASS | Scoped Pint and `git diff --check` passed. |
| MySQL migration recovery | PASS | Up -> down -> up on two disposable schema clones on MySQL 8.4.3; no user data copied; original columns restored by down; scratch database removed. |
| Local activation | PASS | Target migration recorded; all four columns present and Eco enabled. |
| Existing local data | PASS | 69 runs, 30 branches, 137 messages retained; existing runs remained Default. |
| Live provider savings / quality | NOT RUN | Provider responses were mocked; no paid inference used. |
| Production deployment / DDL locks | NOT RUN | Only the local database was activated. |

The local tables use InnoDB and were small (roughly 49 KiB run data and 16 KiB branch
data). No AI run was active during migration. Only the named Eco migration was
applied, with a five-second session metadata-lock wait bound. Its framework-reported
execution took about 109 ms; this is not a production latency or lock guarantee.
Configuration cache was cleared, the queue restart signal was broadcast, and assets
were rebuilt. The local `ai:work` command uses `queue:listen`, loading fresh worker
processes for subsequent jobs.

## Recovery and operational use

Refresh the AI page before sending a new Eco turn. Historical token totals remain
historical; inspect the new run's persisted mode and context metadata for comparison.

To reject new Eco admissions, disable `AI_ECO_ENABLED`, refresh cached configuration,
and restart long-lived workers. Valid admitted runs keep their frozen policy. Keep
the additive columns during an operational rollback. The migration's `down()` was
verified on disposable SQLite/MySQL schemas, and drops only the new mode/policy/cache
columns; it discards Eco metadata, so drain Eco runs before a schema rollback. The
main database was not rolled back or destructively reset.

Validation commands use the isolated source copy with `.env.example`, SQLite memory,
`AI_QUEUE_CONNECTION=sync`, `AI_PROVIDER=deepseek`, and mocked providers:

```powershell
php artisan test --compact tests/Feature/Ai/AiEcoModeReadinessTest.php tests/Feature/Ai/AiEcoModeTest.php tests/Feature/Ai/AiEcoMigrationTest.php tests/Unit/Ai/EcoContextPolicyTest.php
composer test
node --test (Get-ChildItem -LiteralPath tests/Js -Filter '*.test.js' -File).FullName
```
