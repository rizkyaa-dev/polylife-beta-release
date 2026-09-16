# Intent-based coding design

## Architecture

The main conversation model interprets semantics using the relevant owned branch context. Its existing `delegate_code_generation` call can carry optional `design_intent` and a concrete `visual_direction`. No keyword classifier, separate planner inference, demographic color lookup, new package, database migration, or production code-execution service is added.

Pipeline:

```text
Conversation → bounded intent proposal → contextual composition + tokens
             → isolated coder → advisory static HTML diagnostics
```

Ownership:

- `DTOs/AiDesignIntent`: optional, finite vocabulary shared by tool schema and backend validation; bounded audience/assumptions and opaque hex accents. All values remain planner proposals, not verified user facts.
- `Design/AiDesignIntentResolver`: selects task-specific composition and restrained/balanced/expressive guidance. Unknown input uses a neutral, honest fallback, without guessing audience. Reading, showcase, productivity, conversion and information do not share a forced page template.
- `Design/AiDesignTokenCompiler`: computes a 4px spacing family, grouping hierarchy, modular type, context-dependent control recommendations, nested radius, and semantic color roles.
- `Design/DesignColorMath`: sRGB relative luminance and contrast; bounded shade adjustment for readable links, text and control borders. A seed accent is retained as a fill; its readable link variant and on-accent foreground are separate roles.
- `Design/AiDesignArtifactEvaluator`: bounded, non-executing DOM checks for duplicate IDs, literal fragment targets, placeholder links and image alt attributes. Fail-soft, private encrypted step diagnostics, never a blocker for delivery.

`AiCodeGenerationAgent` places the computed plan only in its untrusted user envelope. User-controlled audience, assumptions, colors and visual direction are never interpolated into privileged system instructions. The coder has no workspace tools. Existing source-artifact ownership/branch checks and inference deadlines are unchanged.

Detailed arithmetic verification and duplicated intent are omitted from the model envelope; the model receives presentation decisions and usable tokens, not a numerical audit. Compiler checks can be reproduced from the privately stored brief. This reduces input overhead without weakening local validation.

Static review telemetry is excluded from subsequent main-agent context. The stored coding brief remains available for scoped revisions; diagnostic findings are not silently promoted into conversation content.

## Priority and scope

Explicit user requirements/visual direction beat recommended defaults. Token values are starting points, not a requirement to use cards, rounded containers, every type size, a particular font, extra sections, fixed control heights, animation, or a 60–30–10 palette.

Intent, assumptions and visual direction only authorize presentation, not new features. A live semantic probe initially smuggled an unrequested filter row and dark-mode support into visual direction/assumptions despite a requirements-only scope warning. The main and coder contracts were tightened across **all brief fields**, not with keyword removal of generated source. This remains model guidance rather than proof that arbitrary scope creep is impossible.

Small revisions receive **no replacement token set**. The source design remains authoritative; only requested changes may use the recommendations. This prevents “change this text” or “make it standalone” from triggering an unsolicited redesign. Major design changes must still be requested in the brief.

Unknown interaction uses a 44px control-height recommendation, not an invented mobile audience. Compact mode does not lower the target-size floor. Tokens distinguish a 24px target minimum from a comfort recommendation; WCAG exceptions and platform-native requirements must be evaluated in context.

Native/mobile UI gets intent and platform-neutral advice only when the planner supplies UI intent/direction; it does not receive HTML semantic rules. CLI/library work is excluded. PHP-rendered UI with visual direction is supported.

## What is actually verified

The compiler checks opaque text/muted/link pairs on both base and raised surfaces at 4.5:1, on-accent text at 4.5:1, and necessary control-border pairs at 3:1. Ratios use unrounded values for pass/fail. These checks are **not** proof of rendered accessibility: opacity, gradients, images, states, different colors, font choices and overlay surfaces can invalidate them. Focus uses the readable link role on those recommended surfaces; other adjacent backgrounds need their own check.

The artifact evaluator never executes scripts, requests resources, evaluates CSS, measures actual layout, or proves interaction works. Dynamically created targets may produce advisory static findings. Non-document fragments, multiple HTML documents, oversized output, and missing parser support remain unverified rather than crashing chat. There is no blanket PASS/aesthetic certification and no automatic regeneration loop that can increase cost, drop user features, or consume the existing deadline.

Actual screenshot-based evaluation and bounded visual repair are **not yet implemented in production**. They require an isolated browser worker with a strict network/CPU/memory budget, not PHP executing user-generated code or trusting iframe messages with application privileges. Existing Run preview retains its CSP and sandbox boundaries unchanged. Thus this change implements intent selection, deterministic tokens, coder guidance and static diagnostics—not a full visual-review service.

## Verification and maintenance

Tests cover shared schema/validation vocabularies, invalid or injected values, unknown defaults, semantic profile selection, revision preservation, CLI/library exclusion, native UI, and isolation of audience data from system prompts. A matrix exercises light/dark/unknown modes × neutral/warm/cool/unknown families × six extreme/custom accents, including yellow on white. Geometry and color arithmetic have exact boundary tests. Integration tests verify only main + coder inference and private static-review storage.

Review effectiveness should be measured with the same task set and human/visual rubrics: task fit, hierarchy, composition, readable typography, mobile reflow, functional controls, and visual identity. Unit-test counts and passing color tokens do not demonstrate that generated designs look good.

### Live diagnostic samples (2026-09-16)

Read-only model probes used the installed provider, Low effort, no retries and a 45-second request deadline. MySQL was unavailable locally; the diagnostic process alone used an in-memory cache. Application configuration and conversation records were not changed.

An initial main probe selected showcase correctly but introduced unrequested filtering/theme support through presentation fields. A subsequent pass exposed missing schema length limits and an extra visual-direction key; backend rejected those briefs. Both issues were addressed by whole-brief scope guidance and exposing the existing string/list bounds and allowed keys in the tool declaration. No blind substring removal or relaxation of backend validation was added.

After these changes, three main probes passed brief validation:

| Task | Selected intent | Main elapsed |
| --- | --- | ---: |
| Portfolio, audience unspecified | showcase, neutral, comfortable | 5.246s |
| Long technical documentation on desktop | reading, sustained, comfortable | 7.436s |
| Repeated expense input on mobile | productivity, touch, compact | 5.826s |

A controlled minimal portfolio brief with the final policy and compact plan completed in 29.959s, produced 8,683 characters and finish reason `stop`. All four static HTML checks reported no findings. `data-theme`/`prefers-color-scheme` and two literal internal-plan markers were absent. This limited scan is neither a broad leak audit nor visual approval. An earlier, less constrained coder sample timed out; the policy does not eliminate provider latency or guarantee completion within 45s.

These are single stochastic samples, not an A/B speed claim or capacity benchmark. No screenshot, actual responsive layout, computed CSS contrast, JavaScript runtime or subjective aesthetics was validated in these live probes.

### Regression found during full-suite verification

The full suite exposed an existing legacy-history ordering bug. `AiChatSession::messages()` applies ascending creation-time order; appending `latest()` did not override it, so limits could select the oldest rows. The existing view fixture mass-assigned a non-fillable `created_at`, accidentally making the failure depend on whether inserts crossed a second boundary. Recent and cursor-paginated legacy windows now clear inherited ordering before applying their intended descending sort. Deterministic tests set timestamps explicitly and verify the latest window/nearest previous page; production timestamp mass-assignment remains disallowed.

Final verification after all patches: **337 PHP tests passed (3,837 assertions)**; **9 JavaScript recovery tests passed**; Pint passed for all touched PHP files. The full-suite rerun includes the deterministic legacy-window regressions. No frontend files were changed.

No frontend rebuild or migration is required. Restart persistent AI workers when deploying; local `ai:work` uses a listener that reloads PHP. Future policy changes belong in these small components, not a growing monolithic language router.
