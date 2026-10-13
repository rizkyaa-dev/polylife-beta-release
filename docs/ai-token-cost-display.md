# Rupiah cost display for AI token usage

The token popover optionally estimates the last submission and accumulated session
cost in IDR. The adapter reads the same authoritative totals used by the existing
token display. It does not alter inference, token persistence, API response schemas,
queues, user balances or payments. No database migration is required.

The latest submission cost appears beside the measurement badge. Input and output
costs appear beside their individual token counts, and the session total cost sits
beside the accumulated session token count. There is no separate session-cost row.
Each amount is calculated before currency rounding; displayed input/output amounts
can therefore differ by one cent of a rupiah from the displayed combined total.

## Reference pricing

The profile in `config/ai_cost.php` uses **DeepSeek 4.1 Flash peak pricing** verified
on 2026-10-01: US$0.30 per million input cache-miss tokens and US$1.20 per million
output tokens. Every input token is deliberately priced as a cache miss, so cache
hits and off-peak discounts do not reduce this reference estimate. Reasoning is
already included in completion tokens and is not added again.

Sources:

- [DeepSeek models and pricing](https://api-docs.deepseek.com/quick_start/pricing/)
- [DeepSeek token usage fields](https://api-docs.deepseek.com/api/create-chat-completion/)
- [Bank Indonesia JISDOR](https://www.bi.go.id/id/statistik/informasi-kurs/jisdor/Default.aspx):
  Rp17,937 per US$ on 2026-10-01.

```text
estimated IDR = (prompt × 0.30 + completion × 1.20) / 1,000,000 × USD/IDR
```

12,918 input and 173 output tokens produce approximately Rp73.24 at that reference
rate. Currency formatting uses Indonesian separators and two decimal places.
Session costs are calculated from unrounded session totals, not sums of rounded
display values. Repeated status observations replace values and never add twice.

## Interpretation and configuration

This is a conservative reference estimate, **not an invoice or historical billing
ledger**. Existing tokens are repriced using the displayed model and exchange rate;
the application does not reconstruct historical model selection, exchange rates or
tariffs. Provider changes within a session are not tracked by this display. Actual
DeepSeek charges can be lower due to caching/off-peak rates; payment fees and taxes
are outside the estimate. Changing the configured rate reprices the display.

`unknown` measurements show an em dash; measured zero shows Rp0.00. Partial
measurements show an asterisk and an explanation, since the reference estimate only
covers recorded counters and is not an upper bound on missing usage. Draft estimates
never become recorded monetary usage. Failed/cancelled turns and delegated calls
follow the existing persisted token totals. Invalid profiles or counters never
produce a monetary amount.

```dotenv
AI_COST_DISPLAY_ENABLED=true
AI_COST_USD_TO_IDR=17937
AI_COST_EXCHANGE_RATE_DATE=2026-10-01
```

There is no external currency lookup during page rendering or inference. Update the
rate and date together. Rebuild cached configuration when used. The display is
enabled only for the DeepSeek provider and the configured Flash aliases; Pro and
other providers do not receive Flash pricing.

## Module boundaries and removal

- `config/ai_cost.php`: feature flag, model allowlist and reference profile.
- `resources/js/ai/token-cost.js`: pure calculator/formatter and optional DOM adapter.
- `resources/views/ai/partials/token-cost-summary.blade.php`: reference pricing explanation.
- `resources/css/ai/token-cost.css`: cost-only styling.

Set `AI_COST_DISPLAY_ENABLED=false` to remove all cost markup and profile data;
the existing token counter continues to work. For physical removal, remove the
`initTokenCost` import, initialization and update hook from `workspace.js`, the
conditional cost markup/profile/include from `token-counter.blade.php`, and the
cost stylesheet import from `ai-workspace.css`. Then delete the four module files
and their dedicated tests. No orchestrator or accounting changes need to be undone.

## Verification

JavaScript regression tests exercise the production counter adapter, reference
currency calculations, reasoning/cache handling, partial/unknown/zero counters,
malformed configuration, session/run-only updates and repeated observations.
Blade tests check profile serialization, configurable exchange rates, feature
disablement and isolation from other providers/models. Existing accounting,
session-token, edit-update and theme-contrast tests remain applicable.

```console
node --test tests/Js/*.test.js
php artisan test --compact tests/Feature/Ai/AiTokenCostDisplayTest.php tests/Feature/Ai/AiTokenAccountingTest.php tests/Feature/Ai/AiTokenUsageSummaryTest.php
npm run build
```

No paid-provider request or payment operation is involved in these checks.

Verified on 2026-10-01: all 153 JavaScript tests passed; the targeted PHP suite
passed 18 tests / 115 assertions; the production Vite build, targeted Pint and
Git whitespace check passed. Browser runtime discovery returned no available
browsers, so visual desktop/mobile verification remains unperformed. Rendered
Blade and actual counter handlers are covered by the automated checks.

The inline input/output/session layout follow-up passed all 47 affected JavaScript
tests and 18 PHP tests / 119 assertions, plus a fresh production build. These
checks include the shared input/output calculation and the rendered placement of
each price beside its token count.
