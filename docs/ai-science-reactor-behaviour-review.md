# Reactor behaviour review — 2026-09-18

## Scope

The manual paid-provider probe `tests/Support/science-reactor-behaviour-probe.php` reads the original UTF-8 attachment and exercises the real main model, registered science planner, optional client planner and final narration. The original problem is supplied independently of the main model's delegated interpretation. Reports are private, uniquely named JSON under `storage/app/private/ai-science-reactor-review-20260918/`.

This is not an authenticated chat/UI test, a browser execution test, or a reactor simulation. It does not change deployment flags, users, conversations or production database records. The client planner is exercised directly for unsupported coverage even when the deployment flag is disabled. Generated scripts are never automatically approved by this probe.

## Initial observation

Evidence: `reactor-858844ade0df.json`, total 58.795 seconds. Both planners returned unsupported; neither supplied a program or numerical result. Refusing the requested full research workload was appropriate: missing property functions, kinetic unit definitions and startup conditions prevent a reproducible full numerical model; the requested stiff sparse PDE–DAE, continuation, eigenanalysis and statistical workflows exceed the registered algorithms and bounded client interpreter.

Final narration nevertheless asserted an index-1 DAE without a rank analysis, showed passed dimensional-audit markers without checker evidence, and proposed kinetic dimensionless groups using ambiguous prefactors. Its velocity closure was a tautological restatement rather than an independent closure. It also classified geometrically derivable quantities as independently missing data. Under stated spherical-pellet and cylindrical-tube assumptions, pellet diameter is twice its radius and wall area per reactor volume is 4/D; surface-area definitions still require an explicit volume basis. The planner itself assumed unspecified intrinsic kinetic units were consistent. No numerical reactor outputs were fabricated, but these symbolic claims were not reliable.

## Corrections

- Non-executable tool results now carry a backend-owned evidence boundary: no computation, no certified dimensional audit, no established DAE index. They cannot expose a numerical result, even if an upstream proposal contains one.
- Main-agent instructions distinguish provisional symbolic work from certified checks, require pressure/activity bases for kinetics and equilibrium, and prohibit unsupported DAE-index and model-closure claims. Dimensionless groups require a consistent reference rate or source scale.
- Client planning must consider the entire original task, separate missing data from algorithm limitations, and distinguish independently missing properties from geometrically derivable quantities.
- The probe rejects truncated initial responses and empty, truncated or tool-requesting final responses.

The evidence boundary is enforced in code; final prose quality is still probabilistic. Prompt changes do not prove physical-model correctness or guarantee that future answers cannot contain mistakes. Further progress requires stronger typed model validation and independent scientific review, not interpreting a successful refusal as reactor-solver coverage.

## Repeat observations and remaining limitations

The second live run (`reactor-6f4f4001c8bc.json`, 44.968 seconds) again returned unsupported, with no program/result. Final narration stopped claiming a completed audit, DAE index, stability or solver failure diagnosis. It still copied an inaccurate missing-geometry list. Geometry-specific instructional examples and statistical-data requirements were then added.

The third run (`reactor-d30313a6edf0.json`, 27.622 seconds) returned needs_clarification directly from registered planning, correctly skipping client fallback. The execution/evidence boundary remained intact. However, narration still demanded geometrically derivable quantities and incorrectly said molar-flow inlet velocity required mass density/molecular weight. It also conflated missing numerical property values with inability to write a parametric symbolic model. These are unresolved semantic reliability failures, not successful reactor validation. A subsequent instruction clarifies the EOS molar-flow relation and value-versus-definition distinction; this final instructional adjustment has not been live-retested. Backend boundary regression tests pass, but they do not certify narrative correctness. Typed physical-model evidence remains necessary before claiming this research workflow is reliable.

## Reproduction

```text
php tests/Support/science-reactor-behaviour-probe.php <explicit-attachment-path>
php artisan test --compact tests/Unit/Ai/ScienceEvidenceBoundaryTest.php tests/Unit/Ai/ClientComputationContractTest.php tests/Feature/Ai/AiClientComputationTest.php tests/Feature/Ai/AiScienceDelegationTest.php
```

The live probe incurs provider usage. Inspect final narration separately: `execution_boundary_passed` checks refusal/clarification without a program or result, not semantic correctness of the answer.
