# Chat tables and mathematics

The server uses the same `AiMarkdownRenderer` for stored messages and completed-run responses. GFM tables retain semantic table elements inside a keyboard-focusable horizontal scroll region. Raw HTML and unsafe Markdown links remain disabled.

The CommonMark math extension recognizes `$...$`, `$$...$$`, `\(...\)` and `\[...\]` inside prose before Markdown consumes LaTeX escapes or emphasis. Inline math is limited to a single line; display math can span lines within a paragraph. Code spans, fenced and indented code blocks are not interpreted as mathematics. Dollar-delimited numeric prices are intentionally left as text; use explicit parentheses delimiters for numeric-only mathematics. Unclosed delimiters remain text. Individual expressions are bounded to 8192 bytes by the server.

Only escaped source and renderer-owned math markers are emitted by PHP. The browser lazy-loads a local KaTeX engine and local fonts when math is present. Both initial messages and newly appended assistant messages use the same hydration function. KaTeX output includes MathML, uses untrusted mode, fresh per-expression macro dictionaries, and limits expansion and user-controlled sizes. Invalid expressions retain their original text without interrupting chat. This renders notation; it does not verify scientific claims.

Display formulas and tables scroll within the message rather than widening the page. Focus indicators use existing PolyLife theme variables.

## Verification

- `php artisan test --compact tests/Unit/Ai/AiMarkdownRendererTest.php tests/Feature/Ai/AiWorkspaceViewTest.php tests/Feature/Ai/AiConversationBranchTest.php`
- `node --test tests/Js/ai-math-renderer.test.js`
- `npm.cmd run build`

The existing answer in session 345 was read without modification: its 109 math nodes rendered successfully through KaTeX, and both tables retained semantic columns. Browser screenshot/layout verification was unavailable because the browser runtime reported no connected browsers. This is not a claim of completed visual QA.
