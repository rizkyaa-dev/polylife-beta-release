<?php

namespace Tests\Unit\Ai;

use App\Models\AiChatRunStep;
use App\Services\Ai\Science\AiScienceDelegation;
use App\Services\Ai\Science\ScienceContractStore;
use App\Services\Ai\Science\ScienceSolverRegistry;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ScienceContractStoreTest extends TestCase
{
    public function test_computed_bracketed_root_can_be_shared_with_the_coder(): void
    {
        $inputs = ['expression' => ['op' => 'var', 'name' => 'x'], 'lower' => -1, 'upper' => 1];
        $result = app(ScienceSolverRegistry::class)->solve('root_scalar', $inputs);
        $step = new AiChatRunStep;
        $step->forceFill(['id' => 77, 'tool_name' => AiScienceDelegation::TOOL_NAME, 'status' => 'completed',
            'private_payload' => ['status' => 'ready', 'solver' => 'root_scalar', 'solver_inputs' => $inputs,
                'result' => $result, 'model' => 'Solve x=0.', 'assumptions' => [], 'units' => [],
                'model_verification' => 'unverified', 'version' => 'science-v1']]);
        $contract = app(ScienceContractStore::class)->resolve(77, collect([77 => $step]));
        $this->assertSame('root_scalar', $contract['solver']);
        $this->assertSame('bracketed_interval_checked', $contract['reference_result']['verification']['status']);
        // Do not newly admit historical root contracts made before the pole safeguard.
        $payload = $step->private_payload;
        $payload['result']['kernel_version'] = 'science-kernel-1.7';
        $step->private_payload = $payload;
        $this->expectException(ValidationException::class);
        app(ScienceContractStore::class)->resolve(77, collect([77 => $step]));
    }
}
