# AI execution budget fix

## Changes

The run owns one monotonic deadline. Main planning, code generation, backoff and retries receive that same deadline through immutable request options. Five seconds are reserved for finalization. Every attempt recomputes its remaining timeout; expired work is not started and responses arriving after the operation deadline are rejected.

Automatic retry is restricted to retryable rate-limit/unavailable responses and remaining budget. Ambiguous generation timeouts, connection failures, truncated responses and invalid artifacts are not automatically replayed. The existing user-facing retryable flag remains separate: a user can retry failed work explicitly. Provider retries stay at their existing configured count.

Provider slot TTL covers the entire remaining operation budget plus 15 seconds, not one attempt. Owner-aware finally cleanup remains in place. A run guard checks active status before attempts and after responses, preventing retries and accepted results after cancellation. This does not forcibly stop generation at a remote provider after cancellation or network loss; no upstream cancellation guarantee is claimed.

Coding stage request caps are Off=45s, Low=75s, High=120s, Max=180s. Ordinary planning caps and total run budgets remain unchanged (Low=90s, Max=300s). The actual cap is always bounded by remaining run budget. Reasoning effort and generated-output format/design policies are unchanged.

OpenAI-compatible and Gemini transports classify a known cURL errno 28 as timeout. Other/unknown connection failures use provider_connection_failed; raw headers, credentials and transport text are not copied into the resulting exception. The UI no longer advises lowering effort when a timeout occurs regardless of selected mode.

Validated coding briefs, source IDs and effective effort/request/run budgets are stored in the already-encrypted private step payload before inference, including for failed requests. They are not exposed through public metadata or model instructions. Source ownership checks remain before storage/generation.

## Reproduction after patch

The investigation's real localhost HTTP endpoint and actual application DeepSeek client/wrapper were exercised without paid provider calls. The diagnostic request explicitly carries an operation deadline; no Http::fake was used.

- 3-second delayed response, 2-second request cap: timeout at 2.093s.
- Same payload, 5-second request cap: completion at 3.025s.
- Timeout case with retry configured: only one attempt, 2.006s (previously two attempts, 4.360s).
- Immediate socket reset: provider_connection_failed at 0.004s (previously provider_timeout).

The database-lock reproduction uses isolated SQLite memory tables and a logical test clock. It now retries a known 503 response with an explicit 90-second operation deadline, rather than automatically replaying a timeout. At logical seconds 45 and 61, the nested request is rejected while the original operation remains active; previously the old 60-second lease allowed admission at second 61.

Regression tests cover shared main/coder deadlines, coding-stage caps, encrypted failed-brief retention, no automatic timeout replay, elapsed-time retry clamping, expired budget refusal, concurrency ownership, cancellation before retry/after response, and sanitized transport classification.

Final verification: 348 PHP tests passed (3,889 assertions), 9 JavaScript recovery tests passed, Pint passed for every touched PHP file, and targeted Git whitespace checks passed. Real local HTTP and isolated database-lock experiments passed as described above. No paid-provider end-to-end reliability claim is made.

## Deployment and remaining limits

Restart persistent AI workers after deploying PHP changes. No frontend build, migration, provider credential or prompt-design change is required. Preserve queue retry_after greater than worker execution timeout; the existing database defaults are 420s versus a 330s job timeout. Changing queue backends requires independently checking their effective values.

No streaming or provider-side cancellation was added. These fixes remove reproduced lifecycle defects but do not guarantee that the external provider always completes within its deadline. Paid-provider latency and visual quality across prompts still require controlled live evaluation using the application path and matching configuration, not a separate relaxed diagnostic timeout.
