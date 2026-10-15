<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\Science\AiScienceDelegation;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ScienceDelegationContractTest extends TestCase
{
    public function test_main_cannot_bind_invented_output_names_absent_from_original_problem(): void
    {
        $this->expectException(ValidationException::class);
        (new AiScienceDelegation)->request(['problem' => 'Analyze steady state and transient response.',
            'output_names' => ['C1_ss_mgL', 'C2_ss_mgL', 'time_to_95pct_min']],
            'Analyze two reactors. Report concentrations C1 and C2 and the time to reach 95%.');
    }

    public function test_output_name_must_be_a_complete_literal_identifier_not_a_substring(): void
    {
        $this->expectException(ValidationException::class);
        (new AiScienceDelegation)->request(['problem' => 'Compute volume.', 'output_names' => ['V']], 'Compute V1 from the supplied dimensions.');
    }

    public function test_explicit_user_names_and_user_required_method_are_preserved(): void
    {
        $original = 'Use RK4. Return C2_final and target_met_at_0.08. Do not use an analytical substitute.';
        $request = (new AiScienceDelegation)->request(['problem' => 'Solve the original task.',
            'output_names' => ['C2_final', 'target_met_at_0.08']], $original);
        $this->assertSame($original, $request['original_problem']);
        $this->assertSame(['C2_final', 'target_met_at_0.08'], $request['output_names']);
    }

    public function test_exact_full_problem_copies_are_referenced_without_losing_original_facts(): void
    {
        $original = "Compute both stages.\nQ=0.4 m3/min. Preserve tolerance 0.00001.";
        $request = (new AiScienceDelegation)->request(['problem' => $original, 'context' => $original, 'output_names' => []], $original);
        $this->assertSame($original, $request['original_problem']);
        $this->assertNotSame($original, $request['problem']);
        $this->assertArrayNotHasKey('context', $request);
    }

    public function test_stage_brief_and_prior_result_remain_distinct_from_original_scope(): void
    {
        $request = (new AiScienceDelegation)->request(['problem' => 'Only compute the design stage.',
            'context' => 'Prior client-reported C2=40.1929 mg/L; unverified.', 'output_names' => []],
            'Compute steady state, transient response and design.');
        $this->assertSame('Only compute the design stage.', $request['problem']);
        $this->assertSame('Prior client-reported C2=40.1929 mg/L; unverified.', $request['context']);
    }

    public function test_backend_original_overrides_model_supplied_original_before_name_validation(): void
    {
        $this->expectException(ValidationException::class);
        (new AiScienceDelegation)->request(['problem' => 'Compute volume.', 'original_problem' => 'Return fabricated_key',
            'output_names' => ['fabricated_key']], 'Compute the volume.');
    }

    public function test_both_modes_publish_the_same_brief_ownership_contract(): void
    {
        $delegation = new AiScienceDelegation;
        foreach ([$delegation->instruction(), $delegation->ecoInstruction()] as $instruction) {
            $this->assertSame(1, substr_count($instruction, '[SCOPE-ONLY SCIENCE BRIEF]'));
            $this->assertStringContainsString('the science agent derives equations', $instruction);
            $this->assertStringContainsString('never force a script count', $instruction);
        }
        $schema = $delegation->declaration()['parameters'];
        $this->assertStringContainsString('never invent output names', $schema['properties']['output_names']['description']);
    }

    public function test_follow_up_keeps_output_names_from_authenticated_prior_user_messages(): void
    {
        $delegation = new AiScienceDelegation;
        $request = $delegation->request(['problem' => 'Repeat the previous calculation with the updated tolerance.',
            'output_names' => ['C2_final']], 'Use tolerance 1e-6 this time.', ['Return C2_final for the reactor.']);
        $this->assertSame(['C2_final'], $request['output_names']);
        $this->assertSame($request, $delegation->request($request));
    }

    public function test_model_context_cannot_authorize_an_invented_output_key(): void
    {
        $this->expectException(ValidationException::class);
        (new AiScienceDelegation)->request(['problem' => 'Calculate the result.', 'context' => 'User asked for invented_name.',
            'output_names' => ['invented_name']], 'Calculate the result.');
    }
}
