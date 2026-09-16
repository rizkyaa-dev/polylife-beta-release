# Coding delegation

The main agent receives the bounded conversation lineage and the internal
`delegate_code_generation` declaration on every turn. It decides semantically
whether to answer, clarify, or delegate; keyword routing no longer decides which
agent executes a turn. The main agent produces the complete brief in the tool
arguments, avoiding a separate classification or planning inference.

`AiCodingDelegation` owns the tool schema, input validation and main-agent
boundary instruction. `AiCodingInstructionRouter::forLanguage()` supplies
language-specific implementation policy, with safe generic rules for unlisted
languages. `AiCodingBriefBuilder` bounds and normalizes the brief.

The isolated coding agent receives only the validated brief, current request,
and optionally one selected ancestor artifact. It receives no workspace tools,
personality instructions or full conversation history. Artifact IDs are resolved
against completed assistant messages on the current session lineage, never an
unscoped database lookup. Sources above 120,000 characters are rejected rather
than silently truncated. Results are displayed directly, not rewritten by the
main agent. Copy/download and sandboxed HTML preview remain unchanged.

Briefs and source references are stored in encrypted run-step payloads. The
existing context assembler restores completed tool facts only from ancestor
assistant messages, so edited branches do not inherit sibling coding state.
There is no global mutable coding mode or cross-user cache.

Delegation must be the only tool call in its round. Invalid or mixed calls return
validation feedback within the existing bounded tool loop; workspace writes
still require signed confirmation. Cancellation and the shared turn deadline
apply before and after coding inference. Provider failures remain retryable and
never commit partial generated messages or pending workspace proposals.

As a secondary contract check, substantial fenced implementations returned by
the main agent are discarded and receive at most one delegation-only correction.
Short illustrative snippets, folder trees and logs are exempt. This is not a
perfect classifier: semantic routing remains model-dependent, and a response
that only discusses an implementation request can still require user follow-up.
The boundary check does not execute or prove correctness of generated code.

Regression checks:

```console
php artisan test tests/Feature/Ai tests/Unit/Ai
```

Tests use deterministic provider responses. Validate informal multi-turn prompts,
unknown languages, concept-only questions and topic shifts with the configured
live provider before treating routing accuracy as a measured guarantee.
