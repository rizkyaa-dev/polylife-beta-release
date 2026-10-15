# Science script activity titles

Client-script activities now show an ordinal and a calculation purpose, for example:

- `Skrip 1 · Menghitung volume tabung · belum terverifikasi`
- `Skrip 2 · Menghitung tekanan keluaran · belum terverifikasi`

The process heading also shows the number of client scripts. Counts refer to distinct
client-script activity steps, not model calls, reasoning steps, helper functions inside
one program, or execution retries of the same step. This change does not add parallel
execution or change how the orchestrator delegates multiple computations.

The client-computation planner returns an optional `task_label` of at most 120
characters in the user's language. The existing response validation retains it; no
extra inference call is used to produce or translate a title. The title must name the
actual computed scope, especially when computation coverage is partial.

`ClientComputationPresenter` owns single-line display titles limited to three words
and 48 characters, without an ellipsis. The planner is instructed to name the actual
quantity or formulation within three words, and the presenter enforces the bound
for both new and older metadata. Full model descriptions
remain in the expandable script detail panel. It also owns activity
numbering and outcome labels. It is shared by running/completed JSON responses and
stored-message Blade rendering. The running title is stored in existing public step
metadata when the execution ticket is created. No schema migration is required.

Older steps without `task_label` use their partial-computation scope or stored model
description, then the delegated problem, then a generic fallback. Old records are
not rewritten or sent back to the model. A title is descriptive metadata, not a
certificate: successful browser reports retain `belum terverifikasi`; failures say
`tidak menghasilkan jawaban`, and approval waits remain explicitly labeled.

Program source, inputs, hash, numerical results, verification checks, isolation,
deadlines, confirmation policy and usage accounting are unchanged. The optional title
is outside the hashed program envelope. Blade escapes titles, and client rendering
continues using `textContent`. The expandable source/input/result panel remains intact.

Verification covers different script purposes and sequential numbers, reasoning
exclusion, partial/legacy/failed cases, approval waits, title bounds, running JSON,
completed JSON and stored-message rendering. A read-only probe confirmed the reported
local session has one script with its existing model description as the new title.
No actual computation or paid model call was triggered for this verification.

Targeted PHP tests passed 43 tests and 241 assertions. JavaScript tests passed 168
tests, and the Vite production build passed. The full PHP suite passed 693 tests and
5,804 assertions, with two existing Vite development tests skipped in the isolated
copy. Pint and `git diff --check` passed. Visual browser verification is unavailable
in this tool session.

The follow-up visual regression fixed a Blade directive adjacent to the literal
`dtk`, which had leaked `@if`/`@endif` into rendered process headings. The optional
script-count suffix now uses an escaped expression instead. Regression tests assert
that raw directives never appear and that full descriptions stay accessible while
titles are short. A read-only render of the reported session verified those outputs;
no stored chat data was rewritten.
The follow-up checks passed 501 AI PHP tests (4,679 assertions), with two existing
development-server tests skipped, plus 168 JavaScript tests and the production build.
Pint and whitespace checks passed. Before the fix, the targeted new regressions
produced three failures; afterward all 31 targeted tests passed (204 assertions).
