# Science execution and presentation corrections (session 382)

## Evidence and scope

Run 491 completed in 253263 ms using Eco, nine model calls and four client-script attempts. Stages 1 and 2 succeeded. The first stage-3 script contained the unquoted property `target_met_at_0.08` and failed with `syntax_error`; the replacement succeeded. Independent closed-form calculations agreed with reported concentrations, t95 and the design constant.

Regression tests before the patch exposed ten failures: invalid syntax passed planning, legacy failed receipts lacked a failure presentation, Eco recall retained the original request, science table decimals retained dots, and six client failure variants were persisted as completed.

## Causal corrections

- `ClientProgramSyntaxValidator` uses Node `--check` with strict-mode source on stdin, a timeout capped at 2 seconds and the remaining turn deadline, and a 32-MiB old-space cap. It parses without executing submitted JavaScript. `NODE_OPTIONS` and `NODE_PATH` are removed from the child environment. Syntax diagnostics are sanitized; missing infrastructure raises an error instead of consuming an LLM repair. It does not certify compatibility, algorithm correctness, physics or accuracy.
- The existing single structural-repair allowance also handles parse failures. It retains original facts, inputs, requested output names and the original turn deadline. No extra retry budget is introduced.
- `ScienceExecutionBroker` persists client failures as failed steps while allowing the agent to resume and recover. A failed attempt does not necessarily fail the entire turn.
- `ClientComputationPresenter` derives failed status for older records without a database rewrite and exposes a bounded failure code/message. Completed API responses, running-status responses and Blade use the same derived state. Process headers retain actual attempt counts and add the failed count.
- Model descriptions are prose above the source block. Source, inputs and raw result JSON remain literal, escaped technical content. Client reports remain unverified.
- `ReadableNumberExtension` localizes small decimals in scientific table text and quantities with explicit units. Code, math sources, links, scientific notation, grouped multi-separator values, ambiguous three-digit grouped amounts without a scientific unit, raw JSON and identifiers are preserved. Bare decimals outside tables without a unit are intentionally preserved because they can be versions or technical identifiers.
- Science narration instructions request LaTeX equations, descriptive labels, local number formatting and complete evidence-supported explanations. This is a generation instruction, not a deterministic guarantee against every model writing error. Stored answer text is not rewritten.
- Eco tool-result projection removes `science_request` from recalled results only when the complete original user message is already selected in context. If the original is absent, request scope is preserved. The original problem still reaches the specialist; values, units, scope, checks and verification limits remain. Default projection is unchanged. Live browser resume already omitted this field in the investigated path, so this change must not be described as eliminating every live-round input increase.

## Runtime requirement

Workers that generate browser science programs need Node on PATH, or an executable path in `AI_SCIENCE_SYNTAX_NODE_BINARY`. Parsing is the only server-side JavaScript operation; numerical execution remains in the browser sandbox. Ordinary chat and registered-kernel-only planning do not use the syntax checker. No package installation or database migration is required.

Rebuild frontend assets and restart persistent AI workers after deployment. New generation rules affect new requests; legacy failed-step presentation and numeric table rendering also apply to existing history. The unfinished fraction in the old stored answer remains original evidence.

## Validation

- AI PHP suite in the isolated SQLite installation (production data untouched).
- Node tests including sandbox isolation, invalid property syntax and long-run observation.
- Explicit parse-only regression proves host file writes and top-level throws are never executed.
- Client callback tests exercise six failure reasons, failed API/Blade state, failed-count headers, recovery, prose/source separation, and Eco resume preserving values and original specialist scope.
- Production Vite build, scoped Pint and whitespace checks.

Live browser screenshots and responsive visual QA are unavailable in this environment. HTML/DOM contract tests are not a substitute for real-device inspection. No paid model rerun or savings percentage is claimed.

Read-only checks on the actual run confirmed that the stored failed attempt renders as failed, its syntax is rejected before browser offering, all successful program sources pass parsing, table concentrations/sensitivity render with commas, stored answer content remains unchanged, and Eco recall preserves every result while omitting duplicated request metadata.

Final verification: 512 AI PHP tests passed (4771 assertions), two asset-dependent tests skipped in the isolated installation; 171 JavaScript tests passed. Production Vite build, Pint and whitespace checks passed. A queue restart signal was broadcast locally. Real-browser visual verification and a new paid-provider generation remain unperformed.
