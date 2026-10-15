# Readable AI number display

The chat renderer previously displayed numeric Markdown literally, so the result
`**7318442,8**` appeared as `7318442,8`. `ReadableNumberExtension` now formats
standalone prose quantities with at least five integer digits using Indonesian
grouping: `7.318.442,8`. The shared `AiMarkdownRenderer` applies this to both stored
chat history and newly returned HTML, including numeric table cells.

Formatting happens on parsed CommonMark text nodes before its normal HTML escaping.
Canonical Markdown, computation inputs/results, stored messages, model context and
token accounting are unchanged. No additional model request or migration is needed.
String grouping preserves arbitrary precision, signs and decimal trailing zeros;
it does not cast to float or round. A raw decimal dot is displayed as a comma.

Inline/fenced code, math-expression nodes, link labels/destinations, structured text
containing JSON/array delimiters and bare URI text are excluded. Token boundaries
preserve already grouped values, alphanumeric identifiers, scientific notation,
paths and serial numbers. Leading-zero values are excluded. Four-digit prose is
left unchanged to avoid turning ordinary years such as `2026` into `2.026`.

## Verification on 2026-10-14

Three renderer regression tests cover the reported expression, negative/arbitrary
precision decimals, original grouped numbers, code, math, links, dates, scientific
notation, leading zeros, serials, JSON, arrays, bare URLs and table cells.
With the original renderer, two formatting tests fail and seven renderer tests pass.
The corrected renderer passes the same regression cases without changing the
protected syntax assertions. Tests use an isolated application copy and disposable
SQLite configuration; its dependency manifest matches the workspace installation.
The complete PHP suite passed 689 tests and 5,785 assertions; two existing Vite
development bootstrap tests were skipped in the isolated copy. Pint and
`git diff --check` passed. No frontend assets changed, so a frontend rebuild is N/A.

A read-only probe of the assistant answer in the reported local session found the
original ungrouped result, rendered `7.318.442,8`, and verified that the stored message
content was unchanged after rendering. It made no model call or application write.
An authenticated visual browser check is unavailable because no browser is exposed
to the current tool session.

```powershell
php artisan test --compact tests/Unit/Ai/AiMarkdownRendererTest.php
php vendor/bin/pint --test app/Services/Ai/AiMarkdownRenderer.php app/Services/Ai/Markdown/ReadableNumberExtension.php tests/Unit/Ai/AiMarkdownRendererTest.php
```

Source, regression logs and the read-only probe are reviewable under
`C:/Users/kikid/AppData/Local/Temp/polylife-number-display-20261014/`.
