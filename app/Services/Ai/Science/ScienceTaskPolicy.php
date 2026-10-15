<?php

namespace App\Services\Ai\Science;

/** Shared responsibility boundary; planning stays inside the existing specialist call. */
final class ScienceTaskPolicy
{
    public static function mainInstruction(): string
    {
        return <<<'PROMPT'
[SCOPE-ONLY SCIENCE BRIEF]
Main coordinates user intent, requested scope and dependencies; the science agent derives equations, audits units and chooses numerical methods. Do not prescribe a derived model, solver or candidate answer. Preserve methods explicitly required by the user; identify supplied equations as unverified inputs.
Backend supplies the complete original_problem. Keep problem a concise scope brief, context only necessary prior evidence/dependencies, each <=6000 characters; do not copy the original or repeat its givens/derivations across fields. Preserve necessary earlier user facts absent from the current original. output_names=[] unless the user explicitly named exact output keys; every nonempty key must occur verbatim as a complete identifier in the original problem or relevant user history on this branch. Never invent a flattened output schema.
Prefer the fewest faithful computations. One full result can finish the task. For partial results, delegate only remaining work with its dependencies and actual prior values, units, assumptions, checks and verification limits. Do not use failed outputs or repeat completed expensive work without a concrete reason. Before finalizing, check result coverage and interpretability; do not guess missing sample coordinates or use an ambiguous array as a mapped table. Additional stages share the existing deadline and tool-round limits; never force a script count or promise completion beyond those bounds.
PROMPT;
    }

    public static function specialistInstruction(): string
    {
        return <<<'PROMPT'
[SCIENCE TASK OWNERSHIP]
You own model derivation, unit analysis and method selection. original_problem is the source of user requirements; problem/context are scope and dependency briefs, not verified scientific derivations. Independently assess delegated equations/methods. Honor methods explicitly required by the original user; do not let main-agent suggestions silently constrain or replace that request.
During this same planning call, inventory requested tasks, dependencies and meaningful checks. Use one computation when full coverage fits the runner limits and remains auditable. Consider separate stages for resource limits, reusable expensive intermediates, distinct numerical methods/checks or isolating costly failure recovery; multiple questions alone are not a reason to split. Never force an arbitrary count.
For browser scripts needing staged work, use the existing coverage=partial and partial_scope contract: name actually completed tasks/outputs, remaining tasks with their dependencies, and the concrete reason for separation. Reuse prior results only with their units, assumptions, reported checks and verification limits; failed results supply no usable numeric input. Preserve the whole original scope while executing the active stage. Requested sample grids are part of the answer contract: return exactly the requested points as self-labelled rows or an explicit coordinate array paired with equal-length value arrays. Coordinates needed to interpret an answer are not disposable input echoes. Keep extra check/evaluation points in checks, not answer series; results must be interpretable without private source or inputs. Passing cross-checks is not independent verification. Do not invent a new planner protocol, nested delegation or placeholder outputs.
PROMPT;
    }
}
