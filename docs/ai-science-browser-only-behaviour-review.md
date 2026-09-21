# Browser-only behaviour review — 2026-09-18

## Conclusion and scope

Delegated browser-only computations no longer require registered-kernel planning, arithmetic execution, cache preparation or server replay in the tested routing paths. This does not remove server-side LLM orchestration, encrypted state, queue jobs or result-receipt validation. It also does not make generated algorithms universally reliable.

Seven prompt types were tested with the real provider, preserving original user wording separately from main-agent interpretations. Generated fixture programs were explicitly approved for isolated testing and run in the actual QuickJS/WASM guest engine hosted in Node. These were not authenticated chat requests, actual browser Workers, or visual UI tests. Browser skill setup and documented recovery were attempted; discovery returned no available browsers. The UI consent and real-device runtime flow therefore remain unverified.

Raw reports are private JSON in `storage/app/private/ai-science-browser-review-20260918/`. No production conversations, user data, migrations or telemetry tables were mutated by the live probe. Engine executions for these tests happened in the test host, not on a user's device.

## Live observations

| Prompt | Observation | Evidence filename |
|---|---|---|
| Arithmetic with multiplication, exponent and division | Numeric oracle passed; direct local plan | `arithmetic-8586906e7091.json` |
| 72 km/h to m/s | Initially correct value with wrong requested key; repeat returns `value = 20` | `conversion-cfe7be3b51a8.json` |
| Two-equation linear system | `x = 1`, `y = 2`; residual reports passed | `linear-8a42f1bad134.json` |
| Integral of exp(-x²), 0 to 1 | Initially correct value with wrong key; repeat returns `value ≈ 0.746824132812427` | `integral-a57797f2e3a2.json` |
| RC ODE with SI conversion and numeric integration | Repeats exposed missing schema field, invalid JS property name and ignored output naming; final bound-name run returns `value ≈ 8.646647167633938`, agreeing with independent reference | `ode-966053f93c6a.json` |
| Heating with essential inputs absent | Main asks for missing data without computation; manually reviewed, not counted as a successful tool execution | `missing-ef693becc6bf.json` |
| 100000-state stiff PDE–DAE, continuation and eigenvalues | Unsupported, no program and no fabricated result | `oversized-01151a1b335b.json` |

These are individual observations, not success-rate or load benchmarks. Initial `oracle_passed=false` conversion/integral/ODE reports often reflect output-contract failures rather than wrong arithmetic. The original missing-data probe incorrectly classified a direct clarification as a harness failure; the probe now records that outcome for manual semantic review. No regex is used to claim the clarification is scientifically correct.

## Bugs patched

1. Legacy registered tickets could still expose a browser-kernel program after kernel disablement. Claims are now blocked; page policy independently prevents legacy runner/cache loading. Registered server replay remains disabled.
2. Browser-only pages unnecessarily prepared registered-kernel cache storage. The controller omits that cache scope when kernel routing is disabled.
3. Worker `postMessage` exceptions leaked active state/timers; malformed messages hung until timeout; late callbacks could clear a later run. The client runner now settles once, cleans up handlers/timers, rejects malformed messages and releases resources after transfer failures.
4. Empty or failed reported checks were accepted as `client_computed`. Receipts now expose no answer for those reports. Passing checks still remain unverified client claims.
5. Missing planned-check schema caused terminal planner failures. Exactly one structural correction is allowed within the unchanged deadline/cancellation guard; repeated invalid, truncated and invalid-JSON proposals are not relaxed or executed. No kernel fallback is introduced.
6. Requested output names were lost in delegation and script generation. The tool now has a structured `output_names` field. Nonempty names are bound to the program hash and checked in the guest engine, worker-result receiver and backend receipt. Older callers without names remain compatible. This validates the declared names, not whether the LLM extracted all user requirements correctly.
7. Invalid generated JS was indistinguishable from other execution failures. A bounded `syntax_error` category now travels through guest execution, worker, coordinator and HTTP submission without forwarding arbitrary guest error text.
8. Narration sometimes promised registered solvers despite browser-only policy. Routing instructions now distinguish direct browser computation from kernel fallback and permit direct missing-data clarification without arithmetic.

## Regression coverage and remaining limitations

Integration tests cover local-only success, unavailable browser, cancellation, timeout, syntax/runtime/invalid-input failures, legacy claim/replay rejection, callback idempotence, owner boundaries and restoration of registered-kernel mode. Engine tests cover numeric reference cases, host-API absence, constructor/eval isolation, infinite loops, heap limits and output validation. Mock-worker tests cover transport cleanup and late events.

The final full PHP suite passed 522 tests/4827 assertions; the affected-scope suite passed 57 tests/288 assertions. The final JavaScript suite passed 81 tests and the production build passed. These do not certify actual browser interaction or arbitrary scientific accuracy. Generated source may still fail validation, syntax checks, runtime limits or scientific correctness; runtime failures are safely reported rather than automatically repaired or moved to a server solver. Final explanation remains probabilistic and may add unwarranted interpretations even when numeric oracles pass.

Browser-only deployment settings remain enabled. Rebuilt assets must be refreshed in existing tabs; workers must be relaunched after the restart signal if no supervisor handles that automatically.

## Reproduction

```text
php tests/Support/science-browser-only-probe.php
php tests/Support/science-browser-only-probe.php conversion,integral,ode,missing
php artisan test --compact
node --test tests/Js/*.test.js
npm.cmd run build
```

The manual live probe incurs provider usage and has explicit fixture-only consent. Do not reuse that consent policy for real user programs.
