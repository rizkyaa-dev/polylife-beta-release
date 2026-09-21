# Optional client JavaScript computation

## Two execution paths

Registered science plans retain the existing server-authoritative kernel path. Browser kernel candidates are replayed by the server. This change does not relax their validation or numerical evidence.

When a registered science plan returns `unsupported`, a browser-capable run can invoke an isolated client-computation agent if the dynamic feature flag is enabled. It produces a bounded synchronous JavaScript `compute(inputs)` function, explicit inputs, method/units/assumptions, and a check plan. This is an extension of `delegate_science_problem`, not a new model-facing tool. The original request is retained in its planning envelope. Only one fallback proposal is made; the original run deadline and cancellation guard remain in force.

`needs_clarification`, invalid contracts, numerical nonconvergence and provider failures do not trigger this fallback. The client planner can itself refuse or request clarification. A large research-grade PDE task must not silently become a small geometry computation. Model semantics and adequacy of checks are still LLM proposals, not guarantees; the browser path does not promise full accuracy or capability for session 345.

## Isolation and execution

The user inspects the script, inputs and planned checks before approving each execution. Approval is not remembered globally. Declining, closing the page, cancellation, or a lost lease supplies no numerical result. The execution uses:

- QuickJS 0.32.0 compiled to WASM, running in a dedicated module Worker.
- No host functions, module loader, browser globals, network, DOM, storage or credentials exposed to guest code. Guest `eval` and constructor-created functions stay inside QuickJS; source is never evaluated by the host JavaScript engine.
- A 16 MiB QuickJS allocator limit, 512 KiB stack limit, 5-second interpreter interrupt deadline and 15-second Worker wall deadline including startup. Interpreter limits are not a hard guarantee on total browser/WASM process memory. Worker termination is the outer cancellation/time limit.
- Source <=12000 bytes, input <=8192 bytes, output <=16384 bytes, JSON <=2048 nodes/depth 12. Output is `{values:{...},checks:[{name,passed,residual?}]}` with at most 32 outputs/8 checks and finite numbers.
- Local lazily loaded assets only; Vite Workers use ES format to support the WASM loader's split chunks. No third-party CDN or guest-installed packages.

The browser and server independently validate the result envelope. This checks shape/resource limits, not scientific correctness. Arbitrary JavaScript remains experimental; isolation cannot promise freedom from runtime/browser vulnerabilities. Track dependency updates and keep this path disabled where such risk is unacceptable.

## Ownership, recovery and trust

The existing owner-scoped science execution tickets, lease, callback token, attempt fencing, encrypted checkpoint and queued resume flow are reused. Dynamic claims contain a `client-js-1` program instead of kernel inputs. Dynamic programs/results never enter the numerical cache. Callback retries reuse the same submission without rerunning the script. After lease expiry, backend recovery creates an unavailable-client result; it never executes guest JavaScript on the server or pretends to replay it.

Dynamic receipts have status `client_computed`, result verification `client_reported_only`, model verification `unverified`, and no authoritative runner. Client verification labels are discarded. The server-owned program hash identifies the proposal; it does not attest to honest execution or prove reported checks. Modified/forged client values remain untrusted. These receipts cannot become server-verified science references in `ScienceContractStore`.

The main agent receives values, reported checks and explicit limits, but not source code or private continuation data. Narration instructions forbid verified/full-accuracy claims, treating client strings as instructions, invented givens, or portraying partial arithmetic as full-task success. Instructions alone cannot guarantee flawless narration; semantic evals remain necessary.

Process UI shows a terminal icon and an explicit unverified-local label. Stored answer details show the escaped script, inputs and result both immediately and after reload. Scripts remain in encrypted step payloads and are removed by the existing sensitive-data retention policy; anonymous aggregate telemetry does not retain them.

## Capability feedback

`ai_science_capability_gaps` stores counters per kernel version and allowlisted capability category: unsupported requests, prepared fallbacks, client result receipts and failures/refusals. Categories are model-proposed prioritization hints, not verified diagnoses. It stores no user/run identifiers, prompt text, scripts or answer values. Updates are atomic and optional telemetry failures do not fail chat. Retry acceptance does not count another completion.

This is a prioritization backlog, not automatic training or promotion of generated scripts into the kernel. New kernel components still require review, bounded scope, regression cases and independent numerical evidence. Distinguishing refusals, missing-data outcomes and particular runtime failures in richer anonymous metrics is a future extension.

## Deployment

### Constructive partial computation

Client planning now inventories independent subtasks before refusing a whole problem. Missing numerical properties block dependent numerical solves, not independent geometry, stoichiometry or provisional symbolic reasoning. This is not permission to invent properties or replace the requested physical model.

A ready partial proposal must include `coverage: partial` and `partial_scope` with bounded `completed_tasks`, `deferred_tasks`, `outputs` and `reason`. The backend binds the actual partial output names to the program hash and records `deferred_requested_outputs` from the original delegated output contract. Receipt/engine name checks stay enabled. Full proposals retain the existing exact requested-output contract. Partial task labels are model-proposed scope descriptions, not proof of completed scientific validation; only actual returned numeric values may be reported as computed.

The narrator must distinguish executed support computations from provisional derivation and still-blocked simulation. A successful geometry script cannot establish reactor startup, runaway, stability, inverse estimation or global convergence. No resource limits, host permissions or disabled-kernel routing are relaxed by this change.

Live original-reactor repeat: `reactor-cb89a8d7e9e0.json` returned ready with partial coverage and executed a generated program in the test-host guest engine (83.764 seconds including provider calls). It computed geometry, equilibrium/adsorption temperature corrections, Arrhenius groups and geometric Ergun prefactors, rather than refusing everything. Geometry was independently checked against the supplied radius, porosity and tube dimensions: diameter 0.0025 m, cross-sectional area about 0.00502654824574367 m2, bed volume about 0.030159289474462017 m3, and external area per bed volume about 1488 m-1. It did not integrate the reactor or compute its final requested operating outputs. Final narration still deferred too much symbolic work and implied that data alone unlocked the full solver; a subsequent instruction addresses those claims but has not been live-retested. This is measured partial progress, not complete reactor capability or a browser UI test.

### Zero-click execution

`AI_SCIENCE_CLIENT_AUTO_EXECUTE=true` (default) runs validated client-script claims automatically in the existing isolated worker/QuickJS runtime. The page and the current server claim must both enable automatic execution. No confirmation card appears in this mode. Set the flag false and refresh configuration/workers/page to restore per-program confirmation; the approval component remains available.

Automatic execution does not grant DOM, network, cookie, storage, module-loader or host-process access. Existing memory/time/input/output budgets, cancellation, output-name binding and receipt checks remain unchanged. Failed computation supplies no answer and never falls back to the disabled server kernel. Source/input/result details remain inspectable after completion. The server still orchestrates LLM calls and validates untrusted client receipts; automatic execution does not certify science or make unsupported algorithms available.

Reload existing tabs after deployment so the page receives the new policy and rebuilt scripts. The policy controls execution, not algorithm coverage: the research reactor workload still needs missing data and stronger numerical components before full simulation can be supported.

Zero-click verification: 45 affected PHP tests/245 assertions, 83 JavaScript tests and the production build passed. The coordinator test runs the actual guest engine automatically and retries a lost callback without a second computation or approval handler. Server/page policy revocation is also covered. Live fixture repeats passed arithmetic (`arithmetic-c1590673fb4b.json`, 9.746 s), unit conversion (`conversion-f3be86a2b222.json`, 10.638 s) and RC ODE (`ode-3fc3ad98d2aa.json`, 57.495 s), stored in the private browser-review directory. These times include provider planning/narration, not just computation. Live fixtures execute the guest engine in Node; they do not establish authenticated browser UI/Worker behaviour. Per-program human approval in historical sections below describes the optional manual policy, not the default automatic mode.

### Browser-only policy

Set `AI_SCIENCE_KERNEL_ENABLED=false`, `AI_SCIENCE_BROWSER_ENABLED=true` and `AI_SCIENCE_DYNAMIC_ENABLED=true` to route delegated scientific computations directly to generated client programs. Registered planning, server solving and server replay are bypassed. No browser support, refusal, missing input, cancellation or timeout permits server fallback. Previously queued registered tickets return unsupported rather than replaying; restart those requests in browser-only mode.

The server still orchestrates model calls, stores encrypted execution state and validates result receipts; browser-only means numerical execution, not a serverless application. Consent, sandbox limits and client-reported-only verification remain unchanged. Simple explanations may still be answered without a computation tool. This policy does not promise arbitrary research computations fit the browser or that the LLM will always delegate arithmetic.

When kernel routing is disabled, the page does not prepare the registered-kernel cache. Legacy registered tickets cannot be claimed, and the coordinator independently refuses registered claims under the page's browser-only policy. A computation already started in an older tab cannot be retroactively stopped by changing server configuration, but its registered result will not receive server replay after the policy switch.

To restore registered kernel routing, set only `AI_SCIENCE_KERNEL_ENABLED=true`, run `php artisan config:clear`, then `php artisan queue:restart`. Keep the other flags enabled for registered browser execution and optional dynamic fallback, or set both false for server-only kernel execution. Ensure long-lived workers are relaunched by their supervisor after the restart signal. PHPUnit pins the default kernel-enabled policy independently of local deployment settings; browser-only scenarios override configuration explicitly.

Do not enable before applying the migration and deploying rebuilt assets:

```text
php artisan migrate
npm.cmd run build
```

Set these environment options as desired, refresh configuration and restart long-lived AI workers:

```dotenv
AI_SCIENCE_BROWSER_ENABLED=true
AI_SCIENCE_DYNAMIC_ENABLED=true
AI_SCIENCE_CAPABILITY_TELEMETRY_ENABLED=true
```

All three default to false. Telemetry can be enabled independently of dynamic computation. No production migration, environment change or worker restart is performed by implementing this feature.

## Verification

```text
php artisan test --compact
node --test tests/Js/ai-client-computation.test.js tests/Js/ai-science-coordinator.test.js
```

Tests cover guest arithmetic, API isolation and constructor/eval boundaries, interrupt and allocator limits, invalid/nonfinite/excessive results, absence of trust promotion, default-deny approval, callback retry without recomputation, disabled fallback, missing-data gates, lease expiry, encrypted ticket/resume integration and exclusion from verified coding references. Node VM tests are not browser screenshot or real-device memory/load testing. A production-scale load claim requires separate evidence.

Ready proposals that fail structural validation may receive exactly one correction attempt inside the original deadline and cancellation guard, without relaxed limits or server execution. Truncated or invalid-JSON provider responses are not retried by this repair mechanism. `planning_attempts` records proposal attempts. Reported checks must be nonempty and none may fail before a receipt exposes answer values; passing checks are still unverified client claims, not certificates.

The manual paid-provider probe `tests/Support/science-browser-only-probe.php` exercises several original prompts through live main delegation, direct client planning, the actual browser guest engine hosted in Node, receipt validation and final narration. It explicitly approves only isolated test fixtures and does not mutate production conversations. It is not a browser Worker or authenticated UI test. A direct missing-data response without a tool is retained for manual semantic review rather than falsely marked as successful execution.
