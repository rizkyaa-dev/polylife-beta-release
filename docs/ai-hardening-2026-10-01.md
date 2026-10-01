# AI hardening — 2026-10-01

## Scope and architectural choices

This patch addresses deterministic failures reproduced during the AI audit,
preserving the existing Laravel queue, isolated agents and signed confirmations.
Shared guards own freshness and lock ordering; inference observation owns token
recording; provider adapters validate their envelopes; artifact acceptance owns
bounded structural checks. No generated source is executed on the server.

| Finding | Resulting behavior | Regression evidence |
|---|---|---|
| Timestamp freshness lost same-second edits; task/todo management lacked a version check | Signed proposals bind a SHA-256 snapshot of persisted attributes. Confirmation checks ownership, locks the row and rejects stale snapshots. | Same-second note edit, concurrent task/todo rename and unchanged-record success |
| Budget upsert could overwrite a later edit or a budget created while confirmation was pending | Snapshot captures either the existing row or its absence. Confirmation uses a guarded update or insert; a unique-key collision cannot become an overwrite. | Competing creation, competing edit and unchanged-budget success |
| Run-local token accumulation reset during science continuation; rejected/repair calls lost usage | Each transport attempt is observed before validation and cancellation. A short transaction updates run and step counters. Resume retains previous usage and metadata. | Three calls of 110 tokens persist 330 across browser suspend/resume; coding repair retains both calls; late cancellation retains usage |
| Missing usage appeared as measured zero; session UI could add repeated observations | Counters carry `complete`, `partial`, or `unknown`. Session totals come from one authoritative aggregate, including measured failed runs. Partial totals show a lower bound; unknown totals show an em dash. | Legacy/missing usage, failed-run API, repeated status reads, measured zero and partial JS display |
| HTTP 200 malformed provider responses completed with fallback prose | OpenAI-compatible/DeepSeek and Gemini reject invalid/empty envelopes, classify refusals separately, and retain truncation and invalid-argument repair contracts. | Invalid JSON, missing candidates, scalar content, malformed tool calls, safety refusal and truncation |
| Terminal transitions could acquire run then session while admission did the opposite | Claim, completion, failure, expiry and science ticket transitions share session → run → ticket ordering. Token-only updates never acquire a session lock. | Existing claim, cancellation, stale lease, duplicate delivery and science continuation integration tests |
| Any occurrence of a backtick fence passed coding acceptance | Shared fence parser handles closed backtick/tilde fences. Acceptance requires one nonempty labelled block per brief file in order, appropriate languages and complete HTML envelopes; bounded static checks reject detected HTML defects. | Incomplete fences, missing files, mismatched language, tilde-fence broken links and valid native anchors |
| Invalid HTML findings were advisory only | Coder receives at most one isolated structural repair, preserving the original deadline, scope, no-tools boundary and untrusted prior artifact. A second invalid artifact fails the run. | Static defect is repaired once; both model calls are billed to the same step |
| Downloads ignored brief filenames; multi-file HTML preview lacked its dependencies | Renderer uses the validated manifest on status responses, reload and history. Run is available only for a single HTML file marked runnable. Each file remains independently downloadable. | Filename is retained on API rendering and workspace reload; multi-file output has no partial Run button |
| Production runbook omitted the science consumer and had obsolete timeouts | Runbook includes `ai-compute`, 510-second chat jobs and at least 540-second reservations. Browser observation permits 540 seconds while retaining the original run identity. | Worker/bootstrap and browser recovery suites; production build |

Snapshots bind row attributes, not a transaction-wide snapshot of every relation.
Existing owner checks and confirmation HMAC remain authoritative. File matching
is structural and follows `brief.files` order; it does not prove source semantics.
Static HTML review cannot prove accessibility, visual quality or JavaScript correctness.

## Verification

Tests use isolated SQLite databases, fake provider responses and queue fakes;
they make no paid-provider calls. JavaScript tests exercise the browser computation
and run recovery contracts through deterministic adapters. Production assets were
built with Vite. Targeted Pint and Git whitespace checks passed.

Verification results:

- `php artisan test`: **562 passed, 2 skipped, 4,988 assertions**.
- AI-focused suite before the last documentation/config-language regression:
  **398 passed, 2 skipped, 4,143 assertions**; the final artifact regression suite
  also passed (**4 tests, 9 assertions**).
- `node --test tests/Js/*.test.js`: **92 passed**.
- `npm run build`: passed after the final frontend change.
- Pint for all changed/new PHP files and `git diff --check`: passed.

The skips are existing science worker bootstrap checks requiring an active Vite
development server/hot file; no failing test was marked skipped to accept this patch. The local
MySQL session-token aggregate was also exercised without provider calls.

MySQL lock ordering was inspected in the affected transitions. This patch does
not claim an empirical MySQL contention/load benchmark or thousands-user capacity.
Actual throughput still depends on worker count, shared cache/locks, database
connections, inference duration and provider quota; see [operations](AI_OPERATIONS.md).

## Local application and deployment

The local environment already had migration
`2026_10_01_000001_add_token_usage_to_ai_chat_runs_table` applied. The additional
`2026_10_01_000002_add_token_measurement_state_to_ai_chat_runs` migration was applied
successfully here; its new counters default to zero. Existing usage is not invented
or backfilled. Assets were rebuilt and a queue restart signal was issued.

Other deployments must apply both migrations before serving the new code, rebuild
assets, and restart supervised workers. For a coordinated rollout, pause new AI
admission and drain old workers before activating the new PHP code. Ensure the
science consumer uses the configured `AI_COMPUTE_QUEUE`.

```shell
php artisan migrate
npm run build
php artisan queue:restart
```

Pending proposals created before snapshot binding cannot be safely upgraded:
their original record state is unavailable. They fail validation without changing
workspace records; reject them and request a fresh proposal. Hashes are
server-created proposal data, never supplied by the model.

## Remaining evaluation boundaries

External outages and remote generations continuing after timeout cannot be fixed
by a local patch. Provider usage unavailable on failed/malformed responses is
unknown; counters are not invoice reconciliation, and a superseded worker attempt
cannot mutate current-run counters. Process death before persistence can still
lose telemetry.

Science planners and final narration remain probabilistic. Solver results retain
their existing evidence boundaries; browser-reported checks never become server
verification, and passing structural checks do not certify physical truth.
The historical v1 coding comparison remains historical; no new live-provider,
browser screenshot or scientific-truth evaluation is claimed for v4.
