# Eco inference context

Eco reduces repeated input by selecting complete conversation turns, maintaining an
encrypted index of source quotes, and exposing relevant workspace tools. It uses the
same configured provider, model, thinking effort, coding/science agents, deadlines,
proposal confirmation, authorization, and token accounting as Default.

## Contract and Default compatibility

The authenticated web endpoints `POST /workspace/ai/chat` and `PATCH /workspace/ai/messages/{message}/edit`
accept optional `response_mode: "default" | "eco"`. Their existing response contracts
and status codes remain: 202 accepted, 409 busy/idempotency conflict, 422 validation,
429 capacity/rate limit, 503 system capacity, and sanitized 500 failures. Existing
session/message ownership checks and CSRF protection apply. These are web routes,
not new bearer-token `/api/v1` routes.

The AI page initially selects Eco for users with no saved mode preference. An
explicitly saved Default or Eco choice is restored per account in the same browser.
The toggle and settings slider can switch modes; unavailable browser storage keeps
the choice usable for the current page. Initial Blade controls also display Eco.

Omitted mode still selects Default at the API boundary for compatibility. The browser omits this field for Default and captures
Eco in the pending turn before sending. A lost response replays the original mode
and request UUID even if the user changes the UI selection. Mode changes under the
same UUID conflict. Default keeps the original three-field request fingerprint.

Default continues to use `ConversationContextAssembler::assemble`, the existing
`AiToolSelectionService::declarations`, and the original system instruction builder.
Eco never writes `context_digest` or `digest_through_message_id`. Default does not
read or update Eco memory. The regression test compares the exact Default history,
system instruction, tool declarations, effort and fingerprint against the legacy path.

## Context preparation

1. Admission freezes the Eco policy on the run. A disabled feature or incomplete
   schema rejects a new Eco request with HTTP 422 and `code: "eco_unavailable"`,
   before creating a session, message, branch fork, run or job. There is no silent
   fallback to Default. Previously accepted valid Eco runs retain their frozen policy
   when the feature is disabled. A missing/invalid persisted Eco policy fails visibly
   with `eco_unavailable` and makes no inference call; it never selects Default.
2. The Eco assembler retains the newest complete turns, then ranks earlier complete
   turns by lexical relevance. It adds source quotes and relevant verbatim constraint
   lines, including lines outside an excerpt's head/tail. Memory distinguishes user
   quotes, assistant quotes and artifact references; it does not invent semantic facts.
3. Historical code artifacts are represented by references in a new context segment.
   The existing coding delegation receives the selected exact source artifact. Code,
   science inputs/units/contracts, and selected assistant reasoning are never cut to
   satisfy the soft planning target. Some large tasks can therefore exceed it.
4. Workspace tools are selected using the existing domain catalog. An unmatched
   request receives the small discovery/recall declarations instead of the complete
   workspace schema set. Coding and science delegations remain available.
   Eco uses compact orchestration instructions (profile version 2) instead of the
   full Default instructions plus additional Eco rules. Specialist planning prompts,
   schemas, validation, original evidence and thinking effort remain unchanged.
   Detailed science evidence rules load only after science is called or an existing
   usable science contract is present. Checkpoints preserve the prepared instructions
   and their loaded state without another inference call.
5. `discover_workspace_tools` validates domain names and adds the corresponding
   declarations to subsequent requests. `recall_conversation` validates IDs/query and
   reads only completed ancestors of the run's original parent, within the owned
   session. Foreign, sibling and future messages are unavailable. It can find an
   artifact beyond the initial context window and make its exact source available
   to the coder after authorized retrieval. Coding source IDs outside the initial
   window are resolved through the same owned-ancestor check, including after a
   science checkpoint resumes; this requires no additional inference call.

Recall returns at most four messages with a combined 120,000-character content
limit. Whole messages that do not fit are reported as omitted; their content is not
silently truncated. Query retrieval is lexical, not semantic/vector retrieval, and
is bounded to the configured ancestor window. Missing or ambiguous evidence requires
another narrower retrieval or clarification rather than fabricated details.

Helper tools use the existing activity/step lifecycle, argument validation, duplicate
invocation cache and token counters. One successful helper-only preparation round
can add one reasoning round. The existing per-response/per-run tool-count limits and
original deadline still apply. Default gets no additional rounds. Science checkpoints
freeze the prepared history, tool set and Eco preparation credit; resume restores
them without a second preparation or science planning call.

## Memory lifecycle and privacy

`RefreshEcoConversationMemory` runs on the existing AI fast queue after a completed
Eco turn. It performs no LLM call. The cache stores up to 48 extractive quote/index
entries, source IDs/hashes, version, and a through-message watermark. Read-time checks
verify the watermark and source hashes against the current completed lineage before
processing the new delta. Invalid, stale, deleted-source, or malformed caches rebuild
from authorized source messages. A decryption error is reported and sources remain
available as the recovery path.

Memory is stored separately in `ai_chat_branches.eco_context_memory` using the
encrypted-array cast. It stores neither reasoning nor tool private payloads. Publishing
uses a compare-and-set on branch ID, session ID and the completed head ID, so a delayed
job cannot overwrite newer branch state. Session deletion cascades to the memory.
The existing sensitive-data retention job clears caches using their own refresh time.

The refresh job has three attempts, 5/30-second backoff and a 30-second timeout. A
dispatch failure is reported with the run ID; the completed primary result remains
recoverable and memory can rebuild on the next Eco turn. Memory processing uses the
existing bounded 240-message lineage and does not load the entire chat archive.

Archives and references enter the model as historical data, never privileged system
instructions. Current user corrections and fresh read-tool results take precedence.
All write actions still require the existing signed proposal and user confirmation.

## Configuration, deployment and recovery

`config/ai_eco.php` defines independently configurable planning settings:

```dotenv
AI_ECO_ENABLED=true
AI_ECO_CONTEXT_TOKEN_BUDGET=6000
AI_ECO_RECENT_TURNS=2
AI_ECO_MEMORY_TOKEN_BUDGET=1200
AI_ECO_RETRIEVAL_MESSAGE_LIMIT=1000
```

These are initial engineering bounds, not a measured quality/latency SLO. Configuration
is clamped and copied into each admitted run. The input target covers the system
instruction, tool schemas, original prompt and selected context using the explicitly
labelled three-characters-per-token estimate. It is not an exact tokenizer or provider
context-limit guarantee. Current provider token usage remains authoritative.

Migration `2026_10_11_000001_add_eco_context_to_ai_chat.php` adds:

- `ai_chat_runs.response_mode` with the neutral `default` value;
- nullable `ai_chat_runs.context_policy` JSON;
- nullable encrypted `ai_chat_branches.eco_context_memory` text;
- nullable `ai_chat_branches.eco_memory_refreshed_at` timestamp.

It creates no foreign keys or indexes and performs no application-data backfill.
Only Eco admission probes for the required schema. Default writes remain compatible
with the previous schema. Schema rollout should still precede worker/application
rollout; old workers must be drained/restarted before accepting Eco so they cannot
process Eco runs using older code.

On the intended deployment database, review the engine/version, table sizes and DDL
lock behavior before applying the additive migration. Local testing uses disposable
SQLite; production MySQL/MariaDB lock behavior is not verified by that test. The local
workspace migration and its verification are recorded in `ai-eco-activation-fix.md`.

```sh
php artisan migrate --path=database/migrations/2026_10_11_000001_add_eco_context_to_ai_chat.php
php artisan config:clear
php artisan queue:restart
```

The normal `ai:work` command must consume the existing named AI queues, including
`ai-fast`, for asynchronous refresh. With the `sync` connection, refresh executes
inline, still without an inference call.

To stop new Eco admissions, set `AI_ECO_ENABLED=false`, rebuild/clear cached config,
and restart long-lived workers. Already admitted runs keep their frozen policy and
can complete. Drain them before rolling code/schema back. Prefer retaining the additive
columns during operational rollback. `down()` removes only the new mode/policy/cache
columns; it preserves conversations, proposals and usage, but discards Eco metadata
and cached memory. Back it up first if historical policy provenance must be retained.

## Observability and verification

`ai.eco.context_prepared` records only run/request IDs, policy version, source/selected
message counts/IDs, declaration count, cache hit, estimated input, target and overflow
status, instruction profile version and whether science evidence rules were loaded.
The first reasoning step stores the same context metadata. Conversation text,
reasoning, tool payloads and credentials are excluded from these logs. Unsupported
policy snapshots and memory refresh dispatch failures have explicit warning events.

Existing accounting measures every orchestrator/delegation/retry/helper-round model
call. There is no model-assisted memory call to hide or amortize. Cache hits and total
input should be measured separately; provider caching does not shrink context size.

Tests cover Default payload parity; Eco send/edit validation and idempotency; frozen
flags/policy; dynamic tools; owned ancestor retrieval; exact code source; original
reasoning/constraint retention; encrypted/incremental memory; stale-job protection;
correction/retention/deletion; and science checkpoint replay. The migration is exercised
as up -> down -> Default request/Eco rejection -> up -> Eco request on disposable SQLite.

```powershell
$env:AI_QUEUE_CONNECTION='sync'
$env:AI_PROVIDER='deepseek'
php artisan test --compact tests/Feature/Ai/AiEcoModeReadinessTest.php tests/Feature/Ai/AiEcoModeTest.php tests/Feature/Ai/AiEcoMigrationTest.php tests/Unit/Ai/EcoContextPolicyTest.php
composer test
node --test (Get-ChildItem -LiteralPath tests/Js -Filter '*.test.js' -File).FullName
npm.cmd run build
```

Validation on 2026-10-11 used an isolated source copy, `.env.example`, disposable
SQLite databases, `AI_QUEUE_CONNECTION=sync`, `AI_PROVIDER=deepseek`, and mocked
provider responses:

- Full PHP suite after the activation fix: 675 passed, 5,688 assertions; two Vite-development tests skipped.
- The skipped bootstrap paths were separately exercised with a disposable hot-file
  fixture: all three bootstrap tests passed, eight assertions. This does not represent
  a live development-server or browser-worker integration test.
- Eco/readiness/migration/policy suite: 30 passed, 172 assertions, including exact old-artifact
  resolution after science resume and rejected sibling-artifact IDs.
- JavaScript suite: 168 passed; Vite production build succeeded.
- Pint passed for the changed PHP files; `git diff --check` passed.

The local activation, strict rejection contract, MySQL recovery checks and regression
evidence are documented in [Eco activation fix](ai-eco-activation-fix.md).

The long-history fixture requires an Eco request with less than half the Default
request's character size at the same effort, while preserving the original Default
digest. This is a synthetic payload regression, not a live-provider token saving or
answer-quality benchmark. Broader paired quality evaluation is still needed before
generalizing percentage claims across tasks. Automated tests make no paid provider
requests. A bounded live synthetic-finance comparison and seven multi-call payload
regressions are recorded in [Eco efficiency verification](ai-eco-efficiency.md).

Provider contract references: [DeepSeek thinking/tool transcripts](https://api-docs.deepseek.com/guides/thinking_mode/),
[DeepSeek caching](https://api-docs.deepseek.com/guides/kv_cache/),
[Gemini caching](https://ai.google.dev/gemini-api/docs/caching).
