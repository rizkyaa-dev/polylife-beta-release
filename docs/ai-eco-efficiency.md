# Eco efficiency verification — 2026-10-11

## Cause and change

The previous Eco prompt replayed the full Default orchestration and delegation
instructions plus Eco rules. A financial request already had domain-filtered tools
in Default, so Eco's two additional context helpers made both model calls larger.
The user's paired live runs used 14,766 input tokens in Eco versus 13,914 in Default.

Eco instruction profile 2 now has compact conversation, workspace and delegation
contracts. Detailed science evidence/narration rules are added once when science is
called, or when an existing usable scientific contract is available. A trusted local
boolean controls loading; user-supplied prompt text cannot suppress these rules.
The checkpoint freezes this state and the resulting instruction before suspension.
There is no classifier, summarizer or other extra inference call for this change.

Default's instruction, selection and context paths remain unchanged. Both modes
keep the same model/effort, original specialist prompts, source code, numerical
inputs/units, complete tool results, required reasoning and provider usage counters.
Reads still verify current state; writes still prepare signed confirmation proposals.
Discovery and ancestor-authorized recall remain available. The change needs no schema,
configuration, API or frontend update.

## Live provider measurement

A bounded CLI harness bootstrapped the local application and called its
`DeepSeekLlmClient` (`deepseek-flash`, High effort). It used an in-memory test user,
the same financial prompt and schemas, and a synthetic financial-summary result
with an expected expenditure of Rp325,000. Only `get_financial_summary` was permitted;
no actual workspace tool or user-data query was executed. All profiles obtained the
same correct amount/currency, used two model calls and completed successfully.

| Profile | Total provider input | Input by call | Output | Cached input |
| --- | ---: | --- | ---: | ---: |
| Default | 13,948 | 6,856 + 7,092 | 156 | 6,912 |
| Previous Eco | 14,965 | 7,387 + 7,578 | 108 | 7,424 |
| Eco profile 2 | 8,668 | 4,247 + 4,421 | 90 | 5,760 |

New Eco used **37.9% less input than Default** and **42.1% less than previous Eco**
in this fixture. A separate paired run measured Default 13,893 and Eco 8,669 input
tokens, also with two successful calls and the correct amount. Values come from
provider usage, including cached tokens; they are not tokenizer estimates or reduced
display counters. Provider-generated reasoning accounts for small run-to-run differences.
The old profile was reconstructed from the preserved pre-change source snapshot.

Observed elapsed times in the three-profile run were 2.916s, 2.648s and 2.542s.
These isolated measurements do not establish a latency improvement or percentile SLO.
This validates one live financial scenario, not general answer-quality parity.

## Repeatable whole-turn payload regressions

`AiEcoEfficiencyTest` invokes the real orchestrator/tool/delegation paths with
deterministic responses. It uses the DeepSeek message formatter and sums every
model request, including tool arguments, results, reasoning and specialist calls.
These metrics are **serialized input characters, not provider tokens**. The same
clock, named user, prompt and response fixtures were run before and after the change.

| Scenario | Calls Default/Eco | Default characters | Previous Eco | New Eco |
| --- | --- | ---: | ---: | ---: |
| Financial summary | 2 / 2 | 54,868 | 58,122 | 30,016 |
| Tool discovery then read | 2 / 3 | 93,770 | 81,270 | 39,111 |
| Three financial reads then answer | 4 / 4 | 111,796 | 118,304 | 62,092 |
| Confirmable write proposal | 2 / 2 | 59,705 | 62,959 | 34,853 |
| Short chat | 1 / 1 | 46,701 | 25,406 | 11,353 |
| Main agent and coder | 2 / 2 | 50,704 | 29,409 | 15,356 |
| Main agent, science planner, final answer | 3 / 3 | 120,548 | 77,958 | 52,313 |

Every new Eco fixture must be at least 25% smaller than Default over the entire turn;
observed reductions were 41.6–75.7%. Tests separately require identical financial
tool messages/reasoning, unchanged coder/planner envelopes, identical scientific
evidence (apart from run-owned step IDs), unchanged effort and real usage aggregation.
Write tests require proposals and zero actual note writes. Science detail must be
absent before use, present once afterward, and restored exactly from the checkpoint.

The pre-change isolated implementation produced five failures and two passes.
Two failures were financial payload regressions; discovery and write also exceeded
the saving threshold. Science failed the new staged-instruction contract.
The corrected Eco/readiness/migration/policy/efficiency suite passed 39 tests,
232 assertions, including browser-only execution policy and protection against
user preference text suppressing trusted science rules. The full PHP suite passed
684 tests, 5,748 assertions; two existing
Vite development bootstrap tests were skipped because the isolated copy has no hot file.
Pint passed for all changed PHP files and `git diff --check` passed. JavaScript and
asset builds were not repeated: this optimization changes only backend instructions,
continuation state, metadata and tests; the previous frontend verification remains
recorded in the activation report.

Run tests only against the configured disposable test database:

```powershell
$env:AI_QUEUE_CONNECTION='sync'
$env:AI_PROVIDER='deepseek'
$env:AI_ECO_BENCHMARK_FILE="$env:TEMP/polylife-eco-benchmark.jsonl"
php artisan test --compact tests/Feature/Ai/AiEcoEfficiencyTest.php
```

The benchmark output contains only scenario names, call counts and sizes. Automated
tests use no paid model calls. The live probe artifacts and pre-change source are
under `C:/Users/kikid/AppData/Local/Temp/polylife-eco-efficiency-20261011/`.

## Limits and monitoring

This change reduces repeated prompt overhead; it does not cap or falsify provider
input usage. Essential current inputs, exact code, numerical evidence and reasoning
are retained even if large. An arbitrary task can still require more retrieval,
repairs or evidence than its Default counterpart, so lower total tokens for every
possible turn is not a universal guarantee. Apply identical-input paired tests to
new workloads; track total input and model calls separately from cached input.

`ai.eco.context_prepared` and the initial reasoning step include
`instruction_profile: 2` and `science_evidence_loaded`, alongside the existing
bounded context metrics and run/request correlation IDs. They contain no prompt
text, reasoning, private financial data or credentials. No database/query change was
made, so query plans, concurrency/load tests and migration checks are N/A to this fix.
