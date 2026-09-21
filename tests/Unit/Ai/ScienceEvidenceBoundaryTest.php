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
}
