# Science agent implementation and verification ledger

Status: in progress. This is not a claim that the full scientific runner strategy
or arbitrary scientific problem coverage has been delivered.

The main model receives `delegate_science_problem`. The isolated science model
receives only the contextual problem and registered solver specifications, with
no workspace tools. It proposes bounded JSON. Execution is deterministic PHP,
not model-generated source; results and numerical verification limits are kept
separate from the unverified physical formulation. Tool payloads use the existing
encrypted run-step storage and existing cancellation, deadline and concurrency
guards. No users or workspace records are created by the manual provider probe.

Current coverage: square linear systems, 1..32 unknowns, scaled partial pivoting,
singularity detection, finite arithmetic validation, relative backward residual.
This residual does not prove forward accuracy or physical correctness.

Also implemented: bounded adaptive Simpson integration and nonstiff ODE IVPs
(1..4 states, RK4 step doubling, 2048 attempts, at most 65 returned samples).
Iteration exhaustion never publishes partial integrals or partial final states.
DimensionVerifier checks declared seven-exponent SI vectors, including
dimensionless transcendental arguments and ODE derivative units. Missing
dimensional declarations are explicitly unverified, not passed by default.

Cross-domain calculators can reference `science_step_id`. ScienceContractStore
resolves only current-run results and completed results in the owned branch
lineage (four recent contracts). The coder receives the stored model, inputs and
reference result rather than a model-retyped copy. Scientific prompt policy is
added only when that contract exists, keeping ordinary coding prompts compact.

## Live probes (2026-09-17)

- Spring equilibrium: 3x3 stiffness system, planning plus solve 3033ms; solution
  [0.030555555555555555, 0.022222222222222223, 0.036111111111111115] metres;
  relative backward error 3.17e-17. Model verification explicitly unverified.
- Three-mesh circuit: 16098ms; solution
  [2.5365168539325844, 0.6573033707865169, 1.904494382022472] amperes;
  relative backward error 4.45e-17.
- Missing spring stiffness/connectivity/boundaries: returned needs_clarification
  without computation, 3466ms.
- Unsupported turbulent PDE: first probe exposed overlong irrelevant solver
  fields. Non-ready plans now discard execution fields, with a regression test.
- Unsupported rerun correctly returned unsupported with no computation, 6367ms.
- Work integral 10*x*exp(-x*x), bounds 0..2: 5979ms, value 4.908421805637981,
  137 evaluations, estimated absolute error 4.35e-8. The planner incorrectly
  assigned N/m² to coefficient 10 (it should be N/m for dimensional x, with an
  inverse-square length coefficient in the exponential). Prompt guidance was
  tightened, but an independent dimensional checker is still a completion gate;
  numerical success alone is insufficient. Formula AST supports only a finite
  arithmetic vocabulary (128 nodes, depth 12), never eval or arbitrary functions.
- Structured-dimensional rerun of the same integral: 22338ms; same numerical
  answer, coefficient dimensions corrected, and declared_dimensions_checked.
- Damped oscillator live probe: 9792ms, final displacement -0.33685168440975694 m
  and velocity 0.37069142558015244 m/s; declared dimensions checked. Independent
  analytic unit tests verify exponential decay and the damped oscillator.
- Local compute benchmark: 1000 sequential 8x8 systems, 2531.8ms elapsed,
  p95 2.77ms/job, peak process memory 27.3MB (including Laravel bootstrap).
  This does not measure simultaneous users, inference cost or HTTP capacity.
- Additional live RC transient probe: 22617ms for planning and computation.
  Source 10V, R1/R2/R3=1/2/3 kiloohms, C1/C2=100/220 microfarads, initially
  uncharged, t=2s. Result [8.32498633640785,4.979720827732804] volts agrees
  within 1e-6 with an independent analytic 2x2 KCL matrix-exponential oracle
  [8.324986336363539,4.979720827625142]. Declared dimensions checked; model
  remains unverified. Probe asserts reference agreement, not only ready status.
- Work-integral live rerun with kernel 1.2: 10459ms; 4.90842180563798 J agrees
  within 1e-7 with analytic reference 5*(1-exp(-4))=4.908421805556329 J. Structured
  dimensions checked and coefficient/tolerance units correct in this observation.

## Browser kernel checkpoint (2026-09-17)

Trusted arithmetic AST and the three numerical solvers now have a browser module
runner and module Worker. Kernel revision science-kernel-1.2 is included in both
PHP and JS results and in worker request/response envelopes. Inputs are projected
to bounded fields before structured cloning; arbitrary metadata, source strings,
URLs, non-number coercion and sparse arrays cannot enter numerical execution.
One runner admits one active task, no unbounded queue; abort, dispose, worker
errors, version skew and late results release/terminate the worker. Deadline
checks cover timer throttling and numerical iteration checkpoints. The runner
does not automatically retry or fallback. No browser data cache is installed yet.

Regression fixes in this checkpoint:

- Linear verification must divide by the actual finite nonzero denominator, not
  normal-float minimum: subnormal residuals were understated. Regression oracle
  uses a nonzero residual. Elimination arithmetic now rejects intermediate overflow.
- ODE sample targets use a fixed start and integer sample index, rather than
  accumulating sample intervals or stopping based on absolute time epsilon.
  Tests cover fractional endpoints and a large absolute time offset.
- PHP/JS agree on finite JSON numbers and list shape, without string coercion.
- Simpson weights normalize by integrand magnitude before multiplying the interval,
  preventing intermediate overflow and loss on subnormal intervals. Constant-function
  oracles cover both tiny intervals/large functions and large intervals/subnormal functions.
- Live planner still called mixed-state ODE tolerance dimensionless. The trusted
  solver result now explains componentwise SI-unit targets; planner guidance and
  solver specification make that explicit. Prose alone is not verified.
- Actual Chrome execution exposed Illegal invocation from binding native timers
  as runner methods; wrappers preserve the browser receiver. Mock tests alone
  did not expose this. Real-browser regression now exercises default timers.
- Only usable computed contracts are advertised as ancestor references.
  Cancellation guards run before/after planning and after deterministic solve.

Browser verification builds the production Vite ES module runner and worker,
launches a fresh Chrome headless profile, serves a loopback-only harness and applies
CSP default-src none / script-src self / worker-src self / connect-src none.
Ten scientific/limited-resource cases pass, cancellation permits another solve,
1ms deadline expires safely, no external requests or page errors occur, and 320px
render has no horizontal overflow. Runner+worker are approximately 19KB uncompressed.
This is a kernel harness, not integration into the PolyLife chat UI or a concurrency
capacity claim. Screenshots were rendered and visually inspected.

Evidence: storage/app/private/ai-science-review-20260917/worker-report.json,
worker-desktop.png and worker-mobile.png. Automated JS tests also compare PHP/JS
results and invalid-input rejection. Feature regressions cover owned ancestor
references, discarded sibling rejection, cancellation, encrypted request retention
on provider timeout and rejecting unverified results as calculator contracts.

Latest checkpoint verification: full PHP suite 384 passed / 4148 assertions;
JS suite 34 passed; ten real-browser numerical cases plus lifecycle checks passed.
Sequential local 1000-job 8x8 reference benchmark after stricter validation:
3088.7ms elapsed, p95 4.40ms, peak process memory 27.3MB. This remains a sequential
kernel benchmark, not inference throughput or concurrent-user capacity evidence.

Commands:

```console
node --test tests/Js/*.test.js
php artisan test --compact
php tests/Support/science-provider-probe.php rc-network --summary
node tests/Support/verify-science-worker.mjs <playwright-entry> <chrome-executable> <evidence-directory>
```

Scientific runs use the bounded computational execution allowance (same maximum
180/240/360/480s overall as coding), anchored to original run start. Validated
requests are retained encrypted before inference; cancellation and provider
guards remain active. This is an execution budget, not a changed reasoning level.

These are individual stochastic provider observations, not a throughput benchmark.
Commands: `php tests/Support/science-provider-probe.php equilibrium|circuit|missing|unsupported`
(choose one case per invocation; calls incur provider costs).

## Remaining completion gates

- Broader methods and conditioning/precision coverage beyond the three solvers.
- Independent physical-model verification and scale-conversion provenance beyond
  declared dimensional consistency. Do not treat planner prose as verified facts.
- Broader actual-chat failure and resource coverage beyond the four completed
  end-to-end scenarios below, including multi-tab races and production-like queues.
- Isolated dynamic-script execution, with explicit safety and accuracy limits.
- Queue/HTTP concurrency and crash tests of the implemented server broker. The
  invariant is one accepted result, not a promise of exactly-once execution.
- Live cross-domain calculator generation and actual browser interaction checks
  beyond the already implemented contract sharing and deterministic feature tests.
- Cache ownership, versioning, retention and client cleanup.
- Live medium/complex prompt matrix, browser runtime/visual tests, full suite and
  concurrency/load measurements. No generic “PhD correctness” guarantee.

## Persisted execution checkpoint (2026-09-17)

ScienceExecutionBroker now checkpoints the orchestration history as bounded JSON
in encrypted execution payloads. Ready scientific plans suspend the main job,
offer an owned browser ticket, and resume without another modeling inference.
The original tool call identity, tool-round counter, effort and total deadline
are restored. Clarification/unsupported responses do not create browser tickets.
The legacy server-only path remains available.

Browser claims use UUID client identity, an expiring lease and a scoped token.
Identical claim retries do not renew leases. Identical submission retries do not
enqueue additional verification work, including after completed consumption.
The browser coordinator lazy-loads the numerical runner, rejects cross-origin
callback URLs and retains an uncertain submission for exact replay without
repeating calculation. Edits also advertise browser capability. Callback and
claim endpoints enforce ownership and bounded request bodies.

Client answers never become authoritative directly. The current server completion
replays the trusted solver using the persisted plan, including when the browser
is absent or its lease expires. This increases reliability but does NOT eliminate
server computation cost. There is no cheaper independent client-result verifier
implemented yet. Delayed fallback deliveries and duplicate resumed jobs cannot
publish another accepted result. Compute retries are capped at two server claims.
Recovery scans at most 100 expired tickets per scheduler invocation.

Failure fencing was corrected against Laravel's actual CallQueuedHandler: failed
callbacks reconstruct jobs from their originally serialized payload, so attempt
properties assigned only during handle() are lost. Jobs now carry a UUID generated
before serialization; the active claim stores that token transactionally. A stale
failure callback cannot fail a replacement claim. Main-job tokens are cleared on
suspension, so its late failure cannot invalidate a waiting scientific execution.
Legacy main jobs without tokens rely on existing lease recovery.

Further regressions addressed in this checkpoint:

- Compute failure could mark a ticket failed before the run-level cleanup query,
  retaining its encrypted continuation. Terminal failure now clears it explicitly.
- Resume dispatch now uses the central dispatcher and original effort class,
  preserving heavy/fast isolation and refreshing worker-start tracking. Changing
  preferences while waiting affects neither the snapshot nor resumed queue class.
- The shared oscillator velocity reference was slightly inaccurate; it was
  replaced with the analytic derivative, not an observed solver output.

Verification of this checkpoint:

- Full PHP suite: 400 passed / 4231 assertions; JS suite: 42 passed; Pint passed;
  application Vite production build passed.
- Broker/checkpoint tests: serialization/reconstruction, active/stale failures,
  recovered successor fencing, two-claim exhaustion, cancellation, owned HTTP
  endpoints, oversized callbacks, immutable deadlines/effort, release/resume,
  bounded context and incompatible checkpoint versions.
- Real Chrome regression: ten scientific cases plus actual Worker/coordinator
  execution with a loopback HTTP contract fixture. The first result response is
  deliberately reported as HTTP 503 after acceptance: two submissions share one
  exact body, one calculation and one unique accepted result. No external requests
  or page errors. Desktop/mobile screenshots rendered; mobile 320px inspected.
  This fixture is NOT the Laravel API or actual PolyLife chat UI. Its updated CSP
  permits self connections solely for loopback callbacks; the earlier kernel-only
  checkpoint used connect-src none.
- Live RC-network probe: 19452ms; [8.32498633640785, 4.979720827732804] V, analytic
  KCL oracle passed within 1e-6 and declared dimensions checked.
- Live oscillator probe: 11817ms; state [-0.33685172897202653,
  0.37069155165247597] in m and m/s. Independent analytic state
  [-0.33685168059041337, 0.3706914139692117] passed within 1e-5;
  declared dimensions checked. Model verification remains unverified.

Deployment: apply 2026_09_17_000001_create_ai_science_executions, rebuild assets,
restart AI workers and run the scheduler. The migration was applied only to the
confirmed local environment. Workers must consume AI_COMPUTE_QUEUE (default
ai-compute); ai:work now includes it. Browser execution is feature-gated by
AI_SCIENCE_BROWSER_ENABLED, default false, pending broader resource and production-
like concurrency proof. The four isolated chat scenarios below now pass.
No claim of complete stability, concurrent thousand-user capacity, arbitrary
script safety or universal scientific coverage is made by these checks.

## Actual chat and cancellation checkpoint (2026-09-17)

`verify-science-chat.mjs` now runs the real Laravel HTTP endpoints, CSRF-protected
login, database queue jobs, production assets, browser coordinator and module
Worker. It creates a fresh temporary SQLite database and fresh Chrome profile;
the router refuses any database outside its explicitly guarded fixture setup.
Main/planner/coder inference is a deterministic test fixture, not a live provider.
No existing user's database or browser profile is used.

Four actual application scenarios pass:

- Damped oscillator: browser claim/submission, authoritative server replay and
  resumed final reply; one planner call and analytic x/v reference agreement.
- Work integral: lost claim request followed by page reload; the same owned run
  completes without repeating modeling, with analytic integral agreement.
- Stop during science: a real Worker executes, while the harness holds only its
  message delivery. Acknowledged cancellation terminates the Worker immediately
  (active=0, held=1, terminated=1); no accepted late computation or final inference.
- Science-to-coder calculator: backend-resolved contract, actual sandbox preview,
  analytic x/v outputs, editable time and internal anchor that remains in preview.
  Accessing serviceWorker in that opaque frame correctly throws SecurityError.

The actual chat has no root horizontal overflow at 320px after responsive layout
settles (scrollWidth=innerWidth=320). Code content scrolls inside its code container.
Desktop, mobile and calculator preview screenshots were rendered and inspected.
Delayed fallback queue deliveries are consumed before asserting no failed jobs,
no revived cancelled execution and no additional final inference for that run.
There are no page JavaScript errors. The application login loads its existing
external Bunny fonts; this is not a claim that all of PolyLife is offline.

Production regression patched: acknowledged Stop previously waited for the next
status poll before disposing browser compute. A scoped run-cancellation event now
disposes only that run's coordinator immediately; server observation continues.
The subscription is removed when observation ends, and late results cannot submit.

Harness regressions corrected without weakening application security:

- Mobile geometry was sampled during the shell's padding animation. The test now
  waits for the actual responsive state before measuring, rather than sleeping.
- Playwright serviceWorkers:block injected an unsafe navigator getter into opaque
  sandbox frames. A fresh profile without that injection removes the artificial
  errors; an explicit SecurityError assertion preserves the security check.
- SQLite busy timeout was set only on initialization's connection. It now applies
  to every fixture connection. Deferred read-to-write upgrades can still conflict
  in WAL mode; the fixture reserves writes with IMMEDIATE transactions. PHP <8.4
  needs a test-only PDO/connector adapter to track SQL-started transactions through
  commit/rollback. It is installed before provider boot, only behind the temporary
  database guard, and tested for commit, rollback and nested savepoints. This is
  not a production driver change or proof of MySQL/Redis concurrency behavior.

Additional medium/complex live provider probe: resonant forced oscillator with
m=1 kg, k=4 N/m, F=3*sin(2*t) N, zero initial state, t=5s and local target 1e-8.
Observed planning plus solve: 16383ms. State [2.9425103302465208,
-4.08015833238916] agrees within 1e-5 with independent analytic state
[2.942510317453183,-4.080158331670273]. Structured dimensions pass; physical
model remains unverified. The planner did not invent damping or a finite resonant
steady-state amplitude. PHP tests independently check both states at every sample;
the shared browser/PHP fixture also checks the resonant final state.

Latest verification: full PHP suite 402 passed / 4367 assertions; JavaScript suite
44 passed; real Chrome kernel harness 11 scientific/resource-limited cases plus
lifecycle and idempotent HTTP replay passed; four actual chat scenarios passed;
production Vite build and Pint passed. Existing dependency-data age warnings in
the build were not resolved by unrelated package upgrades.

Evidence: storage/app/private/ai-science-review-20260917/chat-report.json,
chat-science-desktop.png, chat-science-mobile.png, chat-science-preview.png and
worker-report.json. Evidence files are private/local, not checked-in user data.

Commands:

```console
node tests/Support/verify-science-chat.mjs <playwright-entry> <chrome-executable> <evidence-directory>
php tests/Support/science-provider-probe.php resonance --summary
```

Status remains in progress. Load/crash proof on production-like infrastructure,
bounded owned caching, conversion provenance, independent physical formulation
checks, broader numerical methods and isolated arbitrary-script execution remain
completion gates; passing these fixtures does not establish those capabilities.

## Admission and requested-output checkpoint (2026-09-17)

Actual local MySQL 8.4.3 investigations reproduced a cross-user admission race:
two independent processes admitted two runs against a global limit of one under
READ COMMITTED. REPEATABLE READ produced an insertion deadlock rather than a
controlled capacity rejection. Per-user/session locks alone did not serialize
admission across different users.

AiRunAdmissionGuard now takes a seeded global row lock at the beginning of the
short admission transaction. Capacity uses a bounded locking current read, and
the transaction has bounded deadlock retries. No inference or computation holds
the admission lock. Terminal run status releases capacity without a mutable
counter. SQLite acquires its writer before snapshot reads. Missing migration/seed
fails closed. Sixteen independent PHP processes against limit four now yield
four admissions and twelve controlled rejections under both isolation levels,
with no additional sessions/messages or SQL errors. This measures admission only,
not concurrent HTTP/provider throughput or thousand-user capacity.

Migration 2026_09_17_000002_create_ai_run_admission_locks is required. Its seed was
applied only locally. The investigated additional status/id index was redundant:
EXPLAIN selects the existing ai_runs_status_lease_idx. The temporary new index
migration was rolled back locally and removed; no conversation records were
deleted. During deployment, pause admissions while updating all HTTP nodes;
mixed old/new admissions cannot enforce the new global lock invariant.

The live radiative-equilibrium prompt exposed a separate scientific contract bug:
a valid linear reduction computed u=T^4, while the requested temperature T was
left as prose-only postprocessing. A successful intermediate numerical solve was
not a computed final answer. Four initial output regression tests failed before
the patch; one failed-solver safety test already passed.

Kernel science-kernel-1.4 adds optional inputs.outputs: at most eight uniquely
named arithmetic ASTs with declared SI dimensions. r0..rN bind original linear
solution entries, the integral value, or final ODE states. No output chaining,
source code, function names or network operations are introduced. Names, slots,
AST sizes, finite arithmetic and output dimensions are bounded and checked.
Output contracts are checked even when a solver fails; failed solves never publish
derived values. Server replay recalculates outputs rather than trusting browser
values. Backend-resolved science contracts retain these projections, and coding
guidance requires recalculation for changed calculator parameters rather than
hardcoded reference outputs. Output transformations do not certify global error
or physical truth.

Kernel 1.3's dimensional fix is retained: bounded variable-free constant exponent
expressions such as 1/4 are accepted for dimensional bases. Variable exponents
and dimensionful exponents remain rejected. A power-parameter continuation IVP
for radiative equilibrium is also valid: its independent parameter is watts,
not invented physical time or heat capacity. PHP reference tests check all 65
samples against the independent equilibrium family.

Two live provider observations after the output patch passed independent oracles:

- Radiation only, absorbed 40 W, emissivity 0.8, area 0.01 m^2, ambient 300 K:
  linear u=T^4 plus checked fourth-root output gives 557.0334974621942 K;
  analytic reference agrees within 1e-5 K. Planning plus completion: 20016ms.
- Damped oscillator m=1 kg, damping=0.4 kg/s, stiffness=4 N/m, x(0)=1 m,
  v(0)=0, t=5 s: checked state outputs and mechanical energy give
  E=0.2956442878561474 J versus independent analytic E=0.2956441716284185 J;
  both final states and energy agree within 1e-5. Duration: 25816ms.

These are stochastic observations, not success-rate or latency guarantees.
The physical formulation remains unverified. Compact observations are recorded
in storage/app/private/ai-science-review-20260917/output-probe-observations.json;
this is explicitly not a reconstructed original provider transcript.

The shared PHP/JS fixture matrix now has fourteen cases. JS suite: 48 passed,
including rejected malformed/duplicate/excess outputs and failed-solve contracts.
Actual Chrome Worker execution passes fourteen cases, cancellation/reuse,
deadline expiry and lost-acceptance replay with one accepted result. Four actual
Laravel chat scenarios pass with kernel 1.4; inference in that HTTP/UI harness is
deterministic, not a live provider. Updated mobile render was visually inspected.
Production build passed; existing dependency-data-age warnings remain unrelated.

Full PHP regression: 416 passed / 4462 assertions. Final output/broker subset:
21 passed / 95 assertions, including a browser-submitted output value of 999
being replaced by the authoritative computed value 3 before main-agent resume.
Pint passed. The dedicated output subset and PHP/JS rejection parity were rerun
after tightening output validation on failed solves.

Browser capability stays disabled by default. Remaining original completion
gates above are not waived by these additional successful cases.

## Exact expiry and real process-crash checkpoint (2026-09-17)

Four new tests reproduced inconsistent half-open expiry boundaries. At the exact
deadline, execute could compute and recovery could renew the lease; at the exact
client lease expiration, offer/claim/submission remained allowed while fallback
execution treated the lease as expired. Carbon isPast checks strict less-than,
not less-than-or-equal. All four tests failed against the previous implementation.
ScienceExecutionBroker now consistently rejects timestamps <= current time in
offers, claims, submissions, pre/post completion guards and recovery. The original
deadline is not extended. The broker feature suite passes 18 tests / 98 assertions.

verify-science-crash.mjs adds an actual process-termination experiment. In a fresh
guarded SQLite WAL database, it prepares a damped-oscillator plan and starts an
owned PHP child that commits its broker claim before reporting readiness. Only
that confirmed live child is killed with SIGKILL; its actual exit is observed.
The test uses elapsed wall time, not Carbon time travel, and confirms recovery
cannot steal its still-live lease. After the real lease expires, two independent
PHP processes compete to compute the persisted plan: exactly one successor claims
it, giving two server attempts total (killed attempt plus successful successor).
A late failure from the old claim is ignored. The real database queue then drains
all remaining deliveries with one planner inference, one final main inference,
no queued jobs, no failed jobs, and final x/v agreement with the independent
analytic oscillator reference within 1e-7. No existing application database or
browser session is used. The fixture inference is deterministic.

Evidence: storage/app/private/ai-science-review-20260917/crash-report.json.
This proves the tested broker claim/recovery path on SQLite, not Redis/SQS
reservation behavior, a killed queue daemon, concurrent full HTTP inference,
all crash windows, or production-scale capacity. Those original gates remain.

Additional paid live RC-network observation with kernel 1.4: planning/completion
23925ms; final voltages [8.32498633640785,4.979720827732804] V versus independent
KCL matrix-exponential reference [8.324986336363539,4.979720827625142] V. The 1e-6
oracle passes and declared dimensions are checked. Physical modeling remains
unverified; this is one stochastic observation, not a provider throughput measure.

Commands:

```console
node tests/Support/verify-science-crash.mjs
php artisan test --compact tests/Feature/Ai/AiScienceExecutionTest.php
php tests/Support/science-provider-probe.php rc-network --summary
```

Full PHP regression: 420 passed / 4476 assertions. JavaScript regression remains
48 passed; Pint passed. Status remains in progress.

## Replaced-ticket fencing checkpoint (2026-09-17)

Two controlled fault-injection tests exposed missing active-ticket checks beyond
the already verified attempt/token fences. A serialized failed callback whose
token still matched its old ticket could fail a run after its authoritative
execution pointer changed. A second test changes that pointer between the final
unlocked guard's snapshots and the publication transaction: old publication
cleared the replacement run claim and scheduled resume. These are reproduced
invariant violations under injected replacement/race states, not measured reports
of spontaneous production occurrences. Both tests failed before the patch.

ScienceExecutionBroker now rechecks running run status and active execution
identity under the publication/failure transaction lock, preserving the existing
run-before-ticket lock order. Attempt and claim-token checks remain intact.
Replacing a ticket cannot let its old failure or result alter the new run claim.

A third regression exposed exact-deadline handling in AiAgentOrchestrator's
resume admission. Broker expiry was already half-open, but resume used isPast.
At the exact deadline it claimed a new main attempt, then threw provider_timeout
instead of refusing resume with science_execution_expired. It now applies the
same <= expiration rule before claiming or restoring continuation. The three
regressions plus existing broker coverage pass: 21 tests / 118 assertions.

Additional paid live resonant-oscillator observation: 19291ms; final state
[2.9425103302465208,-4.08015833238916] in m and m/s agrees within 1e-5 with
independent analytic [2.942510317453183,-4.080158331670273]. Declared dimensions
checked, physical formulation still unverified. The stochastic observation is
not a routing-rate, accuracy-coverage or capacity guarantee.

Original completion gates remain open; these targeted regressions do not replace
broader capacity, owned caching, physical provenance or isolated-script checks.

## Browser cache, output provenance, and unit-conversion checkpoint (2026-09-17)

Kernel 1.5 tightened derived outputs: every declared output must reference a
solver result slot and remain numerically dependent on it under bounded
perturbation. This rejects both literal candidate answers and expressions that
mention a result only to cancel it. A live damped-oscillator run computed its
energy from final state slots and matched an independent analytic oracle.

The optional browser path now has an IndexedDB result cache scoped by an opaque
HMAC user identifier, kernel version, and SHA-256 of canonical projected input.
Entries contain no raw inputs, expire after one hour, are capped at 32 and 32 KiB,
and are purged when the active owner or kernel changes. Cache data is only an
untrusted candidate: server-side deterministic replay remains authoritative.
Storage failure degrades to normal computation. Multi-tab owner switching can
cause benign misses; this cache is an optimization, not an authorization layer.

Kernel 1.6 adds a finite trusted unit registry and structured
`inputs.conversions`. Each source value/unit is bound to a supported numeric
solver path whose value is already normalized to SI. Scale or offset, declared
dimension, and the bound value are independently checked in matching PHP and
browser implementations. AST paths terminate at a bounded constant `value` and
are capped at 32 segments; the first limit of 16 was rejected by a valid RC AST
and was corrected while retaining the existing AST depth/node limits. Omitted
conversion records remain explicitly unverified; the mechanism cannot prove
that every source quantity was declared or that measurements and the physical
model are true.

A paid live two-node RC observation initially exposed two planner-contract
failures: non-SI values were left unnormalized, then a valid 18-segment AST path
exceeded the provenance bound. After clarifying that conversion records verify
rather than transform and widening only the bounded path limit, the live run
passed. Final voltages `[8.32498633640785, 4.979720827732804]` V agree with the
independent matrix-exponential reference
`[8.324986336363539, 4.979720827625142]` V. Six repeated `kohm`/`uF` source
occurrences were path-bound and checked. This is one stochastic provider
observation, not an accuracy or throughput guarantee.

Latest verification: 432 PHP tests / 4517 assertions and 53 JavaScript tests
pass; Pint and the production Vite build pass. A fresh-profile Chrome run of the
production Worker bundle passes 16 numerical/resource cases, cancellation,
deadline, replay, cache isolation and cache reuse with no external requests or
page errors. The actual Laravel chat harness passes four lifecycle scenarios.
A local sequential 1000-job 8x8 reference benchmark completed in 2952 ms with
p95 4.52 ms and 26 MiB peak process memory. That benchmark measures the bounded
kernel only; it does not demonstrate thousands of concurrent HTTP users,
provider inference capacity, queue throughput, or production database behavior.

Evidence remains under `storage/app/private/ai-science-review-20260917`.
Original completion gates remain open, especially broader numerical methods,
production-like load/queue tests, deeper physical verification, and isolated
dynamic-script execution.

## Bracketed scalar-root checkpoint (kernel 1.7, 2026-09-17)

The fourth registered method is a deterministic scalar root solver using a
sign-preserving bisection bracket. It accepts only the bounded arithmetic AST,
requires ordered finite bounds and either an endpoint root or sign change, and
is capped at 256 iterations. Equal endpoint signs return `not_solved` without a
candidate value; iteration/floating-point exhaustion returns `not_converged`.
Verification reports x-space bracket width, residual, and the absolute x-unit
tolerance while explicitly declining physical correctness or uniqueness claims.
PHP and Worker implementations share dimension, conversion, output projection,
resource and rejection parity. Celsius-to-kelvin bracket conversion is included
as an affine conversion regression.

The live radiative-equilibrium prompt exposed two testable integration issues.
The first planner attempt emitted a variadic multiply although the AST grammar is
binary; the registry and system contract now state exact arity and require nested
binary products. The next correct solve exposed a harness bug that compared the
root's kelvin dimension to the residual's watt dimension. After correcting that
oracle, a fresh live plan selected `root_scalar` and returned
557.0334978401661 K in 27 iterations / 29 evaluations versus the independent
analytic 557.0334974621942 K reference, within its 1e-5 K target.

The real-chat rerun also caught a stale test assumption: its third repeated
oscillator was served from the new cache, so no Worker existed for the test to
cancel. The cancellation scenario now clears only its isolated fixture entry
store first, then verifies actual Worker termination. All four real Laravel chat
scenarios pass again on kernel 1.7.

Latest verification after this checkpoint: 437 PHP tests / 4529 assertions,
56 JavaScript tests, Pint, production Vite build, 18 fresh-profile Chrome Worker
cases, and four actual Laravel chat lifecycle scenarios pass. The Worker bundle
remains about 22 KiB minified in the dedicated harness. These results preserve
the earlier caveat: they do not constitute production-scale concurrency or broad
scientific-method coverage.

The MySQL admission concurrency harness now cleans its guarded random fixture
database and temporary cache directory in a `finally` path, including assertion
failures. A post-change run on MySQL 8.4.3 admitted exactly 4 of 16 simultaneous
independent processes and rejected 12 without orphan sessions/messages; the
capacity query used the status/lease index and the fixture reported successful
cleanup. A clean post-format rerun had a slowest observed request of about
180 ms. This validates exact
backpressure under that burst, not thousands-user throughput: the global
admission row intentionally serializes the short admission transaction and is a
potential hotspot that must be measured on staging before claiming that scale.

A deterministic generated-model regression adds 100 additional numerical cases:
25 diagonally dominant linear systems, 25 polynomial integrals, 25 bracketed
affine roots, and 25 exponential ODEs. Every browser result agrees with its
analytic/reference oracle and the PHP kernel. The seed is fixed so failures are
reproducible; this broadens numerical sampling without pretending to be a proof
over all floating-point inputs.

## Adversarial behavior checkpoint (kernel 1.8, 2026-09-17)

This checkpoint tests the science feature, not generated UI design. The approach
is to retain real provider observations, reproduce defects with independent
oracles, add failing regressions, patch the smallest trusted boundary, and repeat.
Existing unrelated working-tree changes are preserved.

### Proven defects and bounded repairs

| Observed defect | Evidence and repair |
|---|---|
| Tiny conversions accepted zero or a wrong scale | A unit-sized absolute comparison floor accepted `0` and `1e-15` for a conversion whose correct SI value is `1e-18`. PHP and JS now compare relative to the actual nonzero scale and reject conversion underflow to zero. |
| A pole was published as a scalar root | Bisection narrowed around `1/(x-0.123456789)` despite a worsening residual. A narrow bracket now requires residual improvement or an exact zero; otherwise it returns `not_converged` with no value. This safeguard does not prove continuity. |
| Quadrature silently aliased harmonics | The old equally spaced Simpson samples returned `1` with zero estimated error for `cos(8*pi*x)`. Two unequal initial panels now resolve the reproduced 8/12/24/64 harmonics in PHP and JS. The original midpoint domain check is retained. Finite sampling still cannot certify arbitrary oscillations or narrow peaks. |
| Main-agent brief changed the user's problem | Live `unit-conflict-97beee9c62d0.json` invented a hypothetical `3 N/m` correction for an explicitly supplied `3 seconds` coefficient and computed 26 J. The orchestrator now binds the original user wording independently of tool arguments. Delegation and planner instructions require clarification without unsolicited correction. |
| A valid physical task produced an invalid conversion path | Live `coupled-0488eed2c9b1.json` failed before computing because its AST provenance path did not resolve. Ready plans now undergo structural/dimensional/conversion preflight, with exactly one diagnostic-driven repair attempt. Both attempts share the same deadline/guard/options. Provider errors, cancellation and numerical nonconvergence are not retried by this repair mechanism. |
| Scalar-root results could not be reused by the coding agent | The scientific-contract allowlist omitted `bracketed_interval_checked`. Current-kernel checked roots can now be consumed; historical kernel 1.7 roots are not newly trusted by this change. |
| Malformed dimension containers could raise TypeError | ODE state/derivative and linear matrix containers are checked before counting or iterating. Invalid plans produce validation errors rather than an unhandled type failure. |

`planning_attempts` and `planning_repair_errors` expose bounded repair behavior;
`solver_capabilities` comes from the trusted registry rather than planner prose.
Conversion-path diagnostics identify the entry and path without weakening checks.
Preflight is not a replacement for the solver's numerical/input validation and
does not execute the numerical solve or produce a candidate answer.

The numerical changes bump PHP and JS to `science-kernel-1.8`, invalidating
kernel-scoped caches and making version skew follow the existing fallback policy.
They preserve existing evaluation, subdivision, AST and iteration bounds.

### Live prompts, raw evidence and independent checks

Run a case manually with:

```powershell
php tests/Support/science-adversarial-probe.php coupled
```

The ten cases are `missing-injection`, `pole`, `blind-pole`, `oscillatory`,
`stiff`, `unit-conflict`, `blind-unit-conflict`, `distorted-brief`, `rc`, and
`coupled`. Each report retains the exact prompt, public main tool arguments,
backend-bound brief, structured plan/result, final answer, timings and oracle
decision. Hidden reasoning is not retained. Evidence is stored under
`storage/app/private/ai-science-adversarial-20260917` with unique filenames;
earlier failures are not overwritten.
The probe also records separate main-call, planning, deterministic-completion and
final-narration timings in new reports so inference latency is not confused with
the browser/server numerical kernel's cost.

These are paid observations of the production provider client and delegation
policy, not the full user-specific instruction builder or HTTP/UI flow. The
probe uses an in-memory cache only for its own process because local MySQL was
unavailable; production configuration is unchanged. Its 90-second timeout is
per provider call, not a production end-to-end run-budget benchmark.

When the main agent safely answers without calling a tool, the probe also tests
the isolated science planner directly. Reports label `main_skipped_delegation`;
their final answer remains the actual main response. `distorted-brief` deliberately
changes the delegated interpretation, not the backend original. It returns
`needs_clarification` despite that attempted scope change, with no computation
(`distorted-brief-12e93f48e803.json`).

The post-patch complex cases have independent references:

- RC: `[8.32498633640785, 4.979720827732804]` V versus the independent
  matrix-exponential reference `[8.324986336363539, 4.979720827625142]` V.
  An observed repeat (`rc-9756d553f510.json`) takes 98.066 seconds across main,
  planner, deterministic kernel and final narration. The final instrumented repeat
  (`rc-ce1038fc9e43.json`) passes in 69.649 seconds: main 5457 ms, planning
  59197 ms, deterministic completion 38 ms, final narration 4218 ms. This directly
  locates that run's latency in model inference/planning, not numerical execution;
  it is not a provider percentile or throughput benchmark.
- Coupled oscillator: analytic symmetric/antisymmetric modes give the four-state
  reference. The final-state maximum deviation is approximately `6.7e-11`; energy
  is `0.01500000001874889` J versus the analytic conserved `0.015` J.
  `coupled-906c8d2d24d8.json` and `coupled-edbc6eb55df4.json` both pass, at
  76.365 and 109.643 seconds. Both use one planning attempt; the actual repair
  branch is exercised by deterministic tests, not claimed as a live observation.

The oracles check structured outcomes and selected numerical references, not
every assertion in final prose. Manual review caught unsolicited erroneous
sampling-frequency and eigenvalue commentary even when computed values passed.
The response policy now limits narration to requested derivations, returned
results and verification limits, and distinguishes an estimated target from a
certified bound. It also prohibits inventing a quadrature-node trace from an
evaluation count. This is model guidance, not a semantic correctness proof.
Two subsequent oscillation observations compute `2.8260380147138164e-14` with
826 evaluations and estimated absolute error `3.5115440904050959e-9`
(`oscillatory-6a3a811a12c6.json`, `oscillatory-bede0e0a98a0.json`), agreeing with
the independent antiderivative oracle within `1e-8`. Before the final trace-policy
clarification, one answer still invented sampling at `1/8`. These observations
support numerical acceptance but do not close the final-prose correctness gate.

Two initial red reports were harness defects, not science defects: the energy
oracle required the literal output name `energy` despite a valid joule-dimensional
`mechanical_energy` result; an oscillation oracle initially allowed only refusal
although the prompt also permits a correct estimate. The corrected oracles keep
independent numerical/dimensional thresholds. Original red reports remain intact.

### Verification scope and remaining gates

Latest deterministic verification: 447 PHP tests / 4562 assertions and 59
JavaScript tests pass, including 100 generated analytic/reference cases and
PHP/JS parity. Pint and the production Vite build pass. The process-crash harness
also passes on fresh SQLite WAL with real PHP processes and database-queue jobs:
it kills a claimed compute child, waits for lease expiry, fences stale failures,
and drains the queue to exactly one final response matching an oscillator oracle.
Its inference is a deterministic fixture, not live provider evidence; see
`storage/app/private/ai-science-review-20260917/crash-report.json`.

The browser skill was attempted for a current Worker/UI run, but the runtime
reported no available browsers. No actual-browser or rendered-UI verification
is claimed for kernel 1.8 in this checkpoint. Node parity/coordinator/runner tests
are not equivalent to that browser gate. Earlier browser observations above
apply to their recorded kernel versions, not automatically to 1.8.

Acceptance is limited to the tested, bounded methods and safe refusal/clarification
paths. Open gates remain: actual-browser validation for this kernel, production-like
concurrent load/queue/provider behavior, deeper physical-model verification, and
broader numerical coverage. Prompt instructions cannot guarantee every final
sentence. Local error estimates and declared units never certify global error,
model physics, or that all source conversions were declared. Provider latency
remains stochastic and dominates these live observations.

Deployment must publish the matching rebuilt assets and restart long-running
PHP AI/queue workers so they load kernel 1.8 and the updated planning policy.
No production worker restart or service reconfiguration was performed here.

## Domain-model preparation checkpoint (2026-09-17)

The first implementation of the model-verification architecture is now integrated
into the existing plan/complete, browser handoff and coding-contract flows.
See [Scientific model preparation and evidence](ai-science-model-validation.md)
for the contract, trust boundaries, scope and extension instructions.

The new `Science/Models` layer contains an allowlisted Strategy registry,
`ScienceModelValidator` interface, immutable `PreparedScienceModel`, bounded
canonical JSON identity, source quantity binder and `RcLinearModelValidator`.
For supported ideal grounded-capacitor RC networks, the backend builds KCL ODEs
and provenance directly from the declared topology instead of executing a
planner-supplied derivative AST. A wrong-sign or hardcoded candidate AST is
replaced, not endorsed. Unsupported domains retain generic solver inputs with
no fabricated domain evidence. Malformed known-domain contracts fail closed.

`model_evidence.status` is `declared_structure_checked` for a prepared RC model,
with separately scoped quote/quantity, topology, KCL and initial-condition checks.
Source semantics/completeness and numerical applicability remain unverified;
idealizations and implicit zero initial/time interpretations remain explicit.
`model_verification` is still `unverified`, never a universal physical-truth claim.
This addresses equation construction, not automatic proof that an LLM understood
every sentence or selected every component correctly.

Only numeric inputs reach the Worker. Completion rebuilds evidence server-side,
ignoring candidate labels. Raw backend-retained source is private and omitted
from main-model tool history. Coding-contract reuse rebuilds current evidence and
rejects changed equations rather than pairing a new model with an old numerical
reference. A real persistence test caught JSON's integer/float roundtrip making
strict PHP array comparison falsely report changed equations; bounded canonical
comparison fixes it without accepting numeric strings as numbers.

The implementation adds no tables, shared mutable state, locks, inference
reviewers or numerical methods. Model-contract/registry/strategy versions are
`model-v1` / `science-models-1` / `rc-linear-1`; numerical kernel 1.8 is unchanged.
Generated ASTs, dimensions, source values and conversion paths still satisfy
existing PHP and JS resource/provenance rules.

Verification: 487 PHP tests / 4667 assertions, 60 JavaScript tests, Pint and the
production Vite build pass. Tests include analytical RC reference, KCL/passivity
identity at independent state probes, resistor-orientation invariance, state
ordering, malformed/mutated contracts, source-value/unit mismatches, compound
notation and Unicode sign spoofing, JSON persistence, private-source projection,
forged browser results/evidence, and stale coding references. The JS kernel also
executes actual server-generated RC inputs with PHP parity, without gaining
physical-verification authority. This is Node verification, not an actual browser
or UI render.

Two paid provider observations select `rc_linear` in one planning attempt and
match the independent RC transient oracle:
`rc-862cf68c2499.json` (49.732 s; deterministic completion 10 ms) and
`rc-4c692b62931d.json` (47.756 s; planning 33.305 s, completion 11 ms), under
`storage/app/private/ai-science-adversarial-20260917`. Earlier failures are retained:
the planner omitted top-level component connection quotes even though its quantity
quotes were valid; the schema feedback and prompt now distinguish these required
fields explicitly. A separate provider connect/response timeout is also retained.
No validation was relaxed to turn those failures green. The probe now records
bounded public invalid proposals, never hidden reasoning, for diagnosis.

Both successful final answers preserve the limited structure-versus-physical-truth
distinction. One still describes unrequested supplementary checks as requested;
final-prose intent fidelity remains an open gate rather than being covered by the
structured numerical oracle.

A local sequential 1000-job fixed RC preparation/preflight benchmark completes
in 414.1422 ms, p95 0.4965 ms, with 30 MiB peak process memory including framework
bootstrap. It measures neither numerical solve time, provider inference nor
concurrent users. Production-scale load and provider latency remain separate
gates. Restart long-running PHP AI/queue workers on deployment; no production
restart was performed during implementation.
