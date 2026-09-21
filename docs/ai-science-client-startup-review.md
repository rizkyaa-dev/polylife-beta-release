# Client computation startup regression — 2026-09-18

Session 349 / run 423 returned `execution_failed` without a result. Its stored arithmetic program executes successfully in QuickJS and all four checks pass; this does not establish why the original browser failed.

The client now initializes the Worker and QuickJS runtime before sending the guest program. Startup failures map to `unavailable` and permit one replacement Worker within the original wall-clock budget. Once guest execution starts, no automatic recomputation is allowed. Callback retries still replay the original submission, never the program. A rejected engine initialization clears its cached promise.

Failed client receipts publish `client_failed`, not `client_reported_unverified`. Runtime failure text is not copied from untrusted guest exceptions.

Regression coverage includes session 349 arithmetic, startup recovery/exhaustion, execution failure without retry, stale callbacks, malformed messages, cancellation, sandbox restrictions, and zero-click coordinator callback recovery without kernel fallback.

Limit: no browser connection was available during this review. Tests execute QuickJS in Node and simulate Worker lifecycle; production build verification is not a live browser/chat end-to-end test. Missing or obsolete hashed assets remain a hypothesis, not a confirmed cause or a fixed deployment issue.

## Session 350 follow-up

The active hot file selects `http://localhost:5173`, while chat runs at `http://127.0.0.1:8000`. Direct construction of the dev Worker violates the initial Worker same-origin requirement. The dev engine's optimizer dependency URLs also returned `504 Outdated Optimize Dep` during inspection.

Cross-origin development now uses `/ai/science/client-worker.js`, a same-origin Laravel module bootstrap. It buffers initialization messages until the trusted worker module has loaded. The server reads only the configured hot file, checks its origin against the CSP development allowlist, accepts no module URL from callers, performs no outbound requests, and returns no-store JavaScript. This endpoint is unavailable outside the local environment. Production retains bundled Workers without a Laravel bootstrap request or Blob/CSP relaxation.

Vite excludes the two QuickJS entry packages from dependency optimization and separates serve/build cache directories. Restart the existing Vite process after this configuration change; rebuilding production assets does not update an already-running dev server. These changes do not certify numerical/model accuracy or constitute a live chat browser test.
