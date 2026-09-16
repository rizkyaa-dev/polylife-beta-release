# Coding policy v1: implementation and evaluation

## Ownership

- `AiCodingDelegation` asks the main agent to limit the brief to agreed needs and
  their minimum supporting behavior, rather than promoting optional ideas.
- `AiCodingPromptBuilder` owns the stable coder contract and prompt version.
- `AiCodingInstructionRouter` retains language/runtime implementation rules.
- `AiCodingPolicySelector` adds web UI rules for HTML/CSS or browser/PHP visual
  briefs, and revision rules only when a source artifact exists.
- `AiCodeGenerationAgent` retains isolated inference with no tools, validates
  language agreement, and does not pass user brief/source into its system prompt.

This adapts antislop principles, not its install wizard, CLI permissions,
During/After question, file-reading workflow or mandatory audit report. Existing
artifact rendering and preview isolation are unchanged. Prompt instructions are
behavior guidance, not proof of secure output or a prompt-leak prevention boundary.

## Iterative review

1. Implement modular policies and test their selection.
2. Review found that visual direction alone would apply HTML guidance to Flutter;
   restrict web-specific policy selection. Review also found a follow-up-context
   ambiguity; explicitly preserve requirements agreed before the latest message.
3. Live comparison found three `href="#"` placeholders in the new result. Add an
   explicit rule to render missing project URLs as non-interactive labelled text.
4. Repeat live generation and structural checks after the patch.

## Live diagnostic comparison — 2026-09-16

All calls used the configured DeepSeek model, thinking Low, the same minimal
portfolio brief and user request, no tools, and no retry. The brief required one
offline HTML file, hero/projects/contact, responsive layout, working navigation,
honest content placeholders and a mailto contact. Theme switching and reveal
animations were not requested. The transport deadline was 120 seconds for
diagnostics, not the application's Low-effort request timeout.

| Check | Previous prompt | Initial v1 | Patched v1 |
|---|---:|---:|---:|
| Total seconds | 21.583 | 11.293 | 15.329 |
| Answer characters | 22,838 | 10,927 | 9,134 |
| Completion tokens, including reasoning | 7,171 | 3,499 | 3,935 |
| Reasoning tokens | 79 | 85 | 940 |
| Input cache-hit tokens | 0 | 0 | 768 |
| Complete doctype / closing HTML envelope | PASS | PASS | PASS |
| Broken fragment links | 0 | 3 | 0 |
| Mailto contact | PASS | PASS | PASS |
| External src / stylesheet resources detected | 0 | 0 | 0 |
| Inline JS parse-check exit code | 1 | 0 | 0 |
| Unrequested theme/reveal markers detected | yes | no | no |
| Internal contract/envelope markers detected in output | no | no | no |

Old and initial-v1 calls were streamed and both had zero input cache hits. The
patched-v1 call was non-streamed with a cache hit, so its duration is not a
controlled speedup comparison. There was one sample per variant: do not infer
median, tail latency, stable speedup, or thousands-user capacity. Generated source
was checked in memory, not saved into user conversations or executed as a website.

The previous result's JavaScript parse check returned 1; the probe did not retain
stderr, so no specific syntax defect is diagnosed. DOMDocument checks establish
only the properties named above, not full HTML conformance. External-resource
checks do not prove JavaScript cannot make a request. No marker match is not proof
that instructions cannot leak through paraphrase.

Strict acceptance remains **UNVERIFIED** for browser rendering, responsive widths,
computed contrast, keyboard journeys, runtime console errors, mobile/native
generation and broader languages. The patched sample meets the limited structural
checks and avoids the demonstrated placeholder-link defect; it is not certified
production-ready by this evaluation.

## Regression checks

```console
php artisan test tests/Feature/Ai tests/Unit/Ai
node --test tests/Js/ai-turn-recovery.test.js
```

AI suite: 157 tests, 841 assertions passed after the final policy patch. New tests
cover conditional policy selection, CLI/mobile isolation, source-aware revisions,
untrusted envelope separation and rejecting mismatched language policies before
inference. Restart persistent workers after deployment; no schema/frontend change
is required for these prompt policies.
