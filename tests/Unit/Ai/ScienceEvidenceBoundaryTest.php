<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\Science\AiScienceDelegation;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ScienceEvidenceBoundaryTest extends TestCase
{
    public static function nonExecutableStatuses(): array
    {
        return [['unsupported'], ['needs_clarification']];
    }

    #[DataProvider('nonExecutableStatuses')]
    public function test_non_executable_plans_cannot_supply_numerical_or_certification_evidence(string $status): void
    {
        $result = (new AiScienceDelegation)->toolResult([
            'status' => $status, 'model' => 'Unverified planner explanation.',
            'result' => ['values' => ['temperature' => 950]],
            'evidence_boundary' => ['computation' => 'verified'],
            'program' => ['source' => 'private'], 'model_source' => 'private',
        ]);

        $this->assertNull($result['result']);
        $this->assertSame('not_executed', $result['evidence_boundary']['computation']);
        $this->assertSame('not_certified', $result['evidence_boundary']['dimensional_audit']);
        $this->assertSame('not_established', $result['evidence_boundary']['dae_index']);
        $this->assertArrayNotHasKey('program', $result);
        $this->assertArrayNotHasKey('model_source', $result);
    }

    public function test_computed_kernel_evidence_is_preserved(): void
    {
        $input = ['status' => 'ready', 'result' => ['status' => 'computed', 'values' => [1]]];
        $this->assertSame($input, (new AiScienceDelegation)->toolResult($input));
    }

    public function test_eco_drops_duplicate_request_without_losing_execution_evidence(): void
    {
        $input = ['status' => 'client_computed', 'science_request' => ['original_problem' => str_repeat('original ', 500), 'problem' => 'stage 2'],
            'units' => ['mg/L'], 'coverage' => 'partial', 'partial_scope' => ['outputs' => ['C2']],
            'execution' => ['client_failure' => null], 'result' => ['values' => ['C2' => 40.1929], 'reported_checks' => [['passed' => true]], 'verification' => ['status' => 'client_reported_only']]];
        $delegation = new AiScienceDelegation;
        $eco = $delegation->toolResult($input, true);
        $this->assertArrayNotHasKey('science_request', $eco);
        unset($input['science_request']);
        $this->assertSame($input, $eco);
        $this->assertArrayHasKey('science_request', $delegation->toolResult($input + ['science_request' => ['problem' => 'stage 2']]));
    }
}
