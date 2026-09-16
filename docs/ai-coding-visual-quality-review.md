# Coding-agent visual quality review — 2026-09-16

## Scope and approach

This patch distinguishes necessary implementation quality from unnecessary technical complexity. Security, agreed scope, artifact completeness, functionality and accessibility remain mandatory. Technical simplicity is not permission to strip composition, typography, responsive refinement or necessary interaction. Native/inline JavaScript remains allowed, not mandatory; offline/standalone does not forbid it.

Main-agent briefing and coder policy now agree on that boundary. Unknown audience/brand preferences do not force a generic grey design. Unknown palettes retain safe local verification tokens, but fallback hex colors are not sent as a selected creative palette. Explicit user choices and source-aware revisions retain precedence. No tool permissions, application sandbox or production inference deadlines were relaxed.

Screenshot-driven review added two refinements: secondary illustrations must not unnecessarily displace core mobile content, and unknown business facts need concise nearby labels rather than invented claims or active dummy phone links. Explicit facts, including a supplied brand name, must be preserved. User-facing copy should not become an internal page-construction/audit report.

## Actual rendered evidence

The installed application `AiCodeGenerationAgent` generated the HTML through its configured provider. The first successful coffee-shop sample also used an actual main-model delegation. Subsequent diagnostic generation reused a validated, manually tightened brief to remove invented contact requirements and reduce unnecessary specification detail. The request remained an offline coffee-shop information page with six sample menu prices and contact information, without ordering, checkout or testimonials.

Generated HTML was extracted unchanged from the agent's response. It was not hand-edited to improve screenshots. Chrome rendered it in fresh isolated headless contexts at 1440×960, 390×844 and 320×740. The connected in-app browser was unavailable; temporary Playwright tooling outside the repository was used instead. No personal browser profile or application login was loaded. Network, frames, workers and forms were blocked by interception/CSP; inline generated scripts could execute only in the isolated browser realm.

The screenshots were opened and visually inspected, not merely produced from text analysis:

- Initial sample: [desktop](../storage/app/private/ai-design-review-20260916/coffee/desktop.png), [mobile](../storage/app/private/ai-design-review-20260916/coffee/mobile.png), [full page](../storage/app/private/ai-design-review-20260916/coffee/desktop-full.png).
- Refined sample: [desktop](../storage/app/private/ai-design-review-20260916/coffee-quality/desktop.png), [mobile](../storage/app/private/ai-design-review-20260916/coffee-quality/mobile.png), [320px](../storage/app/private/ai-design-review-20260916/coffee-quality/narrow.png), [full page](../storage/app/private/ai-design-review-20260916/coffee-quality/desktop-full.png).

The initial sample had coherent warm colors, readable hierarchy and intact inline illustration/menu cards, but excessive mobile illustration space and unsupported business claims. The refined sample used a smaller mobile illustration, stronger serif display hierarchy and concise menu cards. Dummy contact actions were removed and example business data were labeled nearby. Review also found excessive explanation and incorrect example-labeling of the supplied brand; the policy was tightened again to preserve known facts and avoid audit-like copy.

For both rendered samples, all three widths had no document overflow, broken images, missing fragment targets, script errors or attempted external requests. Keyboard Tab reached the skip link with a visible 3px outline. The refined sample's 31 sampled opaque text/background pairs passed their computed contrast thresholds at each width; gradient, translucent and image-backed text remained unverified. There were no expandable menu buttons in these samples, so menu interaction was not tested. This is not a complete accessibility audit or a general JavaScript-runtime certification.

The comparison is descriptive, not controlled A/B evidence: the brief was tightened and model outputs are stochastic. Neither screenshot approval nor passing token arithmetic guarantees good design for all prompts. The result is a credible basic coffee-shop composition, not a claim of exceptional aesthetic quality or proven long-session comfort.

## Reliability and reproducibility

Successful diagnostic coder requests took 47.030s and 52.366s. Other portfolio/coffee requests timed out at the provider. Diagnostic probes used Low effort, no retries and a 75-second request budget after an initial shorter timeout; production deadlines were not increased. These measurements cannot establish median/tail latency or thousands-user capacity. Provider latency remains a separate unresolved concern.

The final live retry after the known-brand/copy refinement also timed out. That last rule is covered by passing regression assertions, but its generated output has not been rendered successfully. The linked screenshots demonstrate the earlier successful samples, not a falsely certified final-policy result. Further paid retries were stopped rather than creating an unbounded regeneration loop.

Manual tools:

```console
php tests/Support/ai-design-visual-probe.php --check
php tests/Support/ai-design-visual-probe.php coffee
node tests/Support/render-ai-design.mjs <generated-html> <output-directory> <temporary-playwright-entry> <chrome-executable>
```

The live probe incurs provider usage, does not run automatically in the test suite, and creates no users, conversations or workspace records. Screenshot HTML/evidence lives under ignored private storage. The renderer is manual QA tooling, not a production visual-review service or an autonomous regeneration loop. Temporary tooling did not change application dependencies.

Verification: 339 PHP tests passed (3,856 assertions), 9 JavaScript recovery tests passed, Pint passed for touched PHP files, and the screenshot runner passed Node syntax validation. Prompt selection/isolation, palette handling and the quality/refinement rules have regression coverage. The HTML system prompt remains within its existing 8,500-byte test budget.

No sidebar/frontend files, migrations or production preview-security boundaries were changed by this patch. Restart persistent AI workers when deploying PHP prompt changes. The sample evidence is intentionally retained locally for review and is not included in Git.
