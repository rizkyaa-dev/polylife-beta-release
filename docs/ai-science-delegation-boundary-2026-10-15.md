# Science delegation ownership and scope

## Trigger

Session 383 / run 492 correctly completed the two-CSTR problem in one script, but its main-agent brief already prescribed derived equations, eigen methods and 24 invented output keys. This violated the existing responsibility boundary and duplicated the original question. Script count itself was not a defect.

## Changes

`ScienceTaskPolicy` centralizes the main/specialist responsibility boundary. Both Default and Eco reuse the same scope-only brief rules. Registered and browser specialists receive the same ownership policy inside their existing planning call; there is no additional planner agent or mandatory model round.

Main preserves user intent, scope, dependencies and user-required methods. Science derives and reviews equations, checks units and selects methods. Main-supplied interpretations do not become verified facts. Models and methods are not enforced with brittle equation/keyword stripping.

`AiScienceDelegation::request` now rejects nonempty output names that do not occur literally as complete identifiers in the original problem or authenticated prior user messages from the same branch. Model-authored context is never an authority. Internal planner revalidation preserves the already admitted binding. It returns actionable validation feedback rather than silently renaming keys or weakening a result contract. This is lexical provenance, not semantic proof that a matching word was intended as an output; the instruction still requires explicit user naming. A trusted backend original replaces any model-supplied original before this validation.

Whitespace-equivalent complete copies of the original are represented by a short full-scope reference, retaining the original byte-for-byte. Context is omitted only when it duplicates the complete original or the scope brief. Distinct stage briefs, prior values, units, assumptions and verification limits remain. No fuzzy rewriting or silent change of scientific scope is introduced.

The specialist considers resource limits, reusable expensive intermediates, distinct numerical methods/checks and failure recovery when deciding whether to split. One faithful complete program remains valid. Existing `coverage: partial` / `partial_scope` fields describe actual progress and dependencies; no new DAG or nested-delegation protocol is introduced. Additional stages retain the existing turn deadline and round limits.

Sampled answers must be interpretable without private source or inputs: use self-labelled rows or explicit coordinates paired with equal-length value arrays, containing the requested sample points. Extra evaluation points belong in checks. Main must not guess missing coordinates when rendering a table. Coordinate labels essential to the answer are not forbidden input echoes.

## Regression evidence

Before the production changes, four of six new contract tests failed: invented names were accepted, substring names were accepted, complete original copies remained duplicated, and a model-supplied original was not enough to prevent ungrounded names. The existing scope/original-preservation tests passed.

Regression coverage includes valid user-specified names and methods, safe compaction, both instruction profiles, one-program completion, and two-script continuation using actual prior values with original-scope preservation and unchanged deadlines. The small arithmetic continuation fixture tests lifecycle mechanics; it does not assert that arithmetic should be split by a real planner.

## Limits

Semantic modeling and method choice remain model behavior, tested through bounded provider observations rather than declared guaranteed by JSON validation. No claim of universal token savings, independent physical verification or a required number of scripts is made. No schema migration, provider change, dependency installation or UI change is required.

## Provider observations

A bounded synthetic main-to-specialist observation used the configured provider, Low effort and only the science tool. No application conversations, users or settings were written. Main left `output_names` empty, supplied a scope brief and left equations/methods to science. The specialist produced one full browser program without an additional planning phase.

The first observation returned unlabelled concentration arrays including an extra evaluation time. Sampling guidance was strengthened, and the specialist was rerun with the same observed main brief. It returned exactly `t_min=[0,5,10,20,40]` with matching concentration arrays. QuickJS replay completed in about 364 ms with all six reported checks passing. An independent 2x2 matrix-exponential reference confirmed all requested samples, steady concentrations, t95, design k2 and baseline sensitivity within the supplied tolerances; maximum sample discrepancy was below 1e-12 mg/L.

These are bounded model observations, not a guarantee for all future prompts. The probe omits the full workspace instruction builder, unrelated tool declarations and final narration; its token counts are not a valid end-to-end savings comparison against session 383. Matching this reference does not promote arbitrary client reports to independently verified science.

Operational scope: these rules apply to science delegation in both response modes. Existing model/effort, time and round budgets, tool isolation, receipt verification boundaries and UI are preserved. Persistent workers need the normal queue restart to load PHP changes; already suspended checkpoints retain their previous prompt snapshot.

Final checks: 523 AI PHP tests passed (4810 assertions), two asset-dependent tests skipped in the isolated SQLite installation. Scoped Pint and git whitespace checks passed. No frontend rebuild was needed for this backend/prompt-only patch. A local queue restart signal was broadcast. The provider probe exercised the science-only harness, not the full workspace UI or a controlled Default/Eco cost benchmark.
