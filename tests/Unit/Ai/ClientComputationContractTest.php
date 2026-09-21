<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\Science\Client\ClientComputationContract;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClientComputationContractTest extends TestCase
{
    public function test_receipts_cannot_promote_client_verification_claims(): void
    {
        $contract = app(ClientComputationContract::class);
        $plan = $contract->validate(['status' => 'ready', 'model' => 'Geometry', 'assumptions' => [], 'units' => ['m2'],
            'program' => ['source' => 'function compute(i){return i}', 'inputs' => ['D' => .08], 'checks' => ['geometry']]]);
        $result = $contract->receipt($plan, ['values' => ['A' => .005], 'checks' => [['name' => 'positive area', 'passed' => true]],
            'verification' => ['status' => 'physically_verified'], 'authoritative_runner' => 'server'], null);
        $this->assertSame('client_computed', $result['status']);
        $this->assertSame('unverified', $result['model_verification']);
        $this->assertSame('client_reported_only', $result['result']['verification']['status']);
        $this->assertNull($result['execution']['authoritative_runner']);
        $this->assertArrayNotHasKey('program', $result);
        $this->assertSame(64, strlen($plan['program']['hash']));
    }

    public function test_missing_client_result_never_triggers_a_fake_server_calculation(): void
    {
        $result = app(ClientComputationContract::class)->receipt(['program' => ['hash' => str_repeat('a', 64)]], null, null);
        $this->assertSame('unsupported', $result['status']);
        $this->assertNull($result['result']);
        $this->assertSame('unavailable', $result['execution']['client_failure']);
    }

    public function test_non_ready_plans_discard_scripts(): void
    {
        $plan = app(ClientComputationContract::class)->validate(['status' => 'unsupported', 'model' => 'Too large', 'assumptions' => [], 'units' => []]);
        $this->assertArrayNotHasKey('program', $plan);
    }

    public function test_excessive_script_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(ClientComputationContract::class)->validate(['status' => 'ready', 'model' => 'Geometry', 'assumptions' => [], 'units' => [],
            'program' => ['source' => str_repeat('a', 12001), 'inputs' => [], 'checks' => ['geometry']]]);
    }

    public function test_nonfinite_client_result_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(ClientComputationContract::class)->receipt(['program' => ['hash' => str_repeat('a', 64)]], ['values' => ['x' => INF], 'checks' => []], null);
    }

    public function test_requested_output_names_are_bound_to_program_and_enforced_on_receipt(): void
    {
        $contract = app(ClientComputationContract::class);
        $plan = $contract->validate(['status' => 'ready', 'model' => 'Addition', 'assumptions' => [], 'units' => [],
            'program' => ['source' => 'function compute(){}', 'inputs' => [], 'checks' => ['inverse']]], ['value']);
        $this->assertSame(['value'], $plan['program']['output_names']);
        $this->expectException(ValidationException::class);
        $contract->receipt($plan, ['values' => ['renamed_value' => 3], 'checks' => [['name' => 'inverse', 'passed' => true]]], null);
    }

    public function test_partial_computation_binds_actual_outputs_and_preserves_unfulfilled_requests(): void
    {
        $contract = app(ClientComputationContract::class);
        $plan = $contract->validate(['status' => 'ready', 'coverage' => 'partial', 'model' => 'Geometry only', 'assumptions' => [], 'units' => ['m2'],
            'partial_scope' => ['completed_tasks' => ['Cross-sectional area'], 'deferred_tasks' => ['Transient needs transport properties'],
                'outputs' => ['area'], 'reason' => 'Geometry independent of unavailable transport data'],
            'program' => ['source' => 'function compute(){}', 'inputs' => [], 'checks' => ['geometry']]], ['T_max', 'X_A']);
        $this->assertSame(['area'], $plan['program']['output_names']);
        $this->assertSame(['T_max', 'X_A'], $plan['partial_scope']['deferred_requested_outputs']);
        $receipt = $contract->receipt($plan, ['values' => ['area' => .005], 'checks' => [['name' => 'geometry', 'passed' => true]]], null);
        $this->assertSame('partial', $receipt['coverage']);
        $this->assertArrayNotHasKey('T_max', $receipt['result']['values']);
    }

    public function test_missing_or_failed_reported_checks_do_not_supply_an_answer(): void
    {
        foreach ([[], [['name' => 'conservation', 'passed' => false]], [['name' => 'conservation', 'passed' => 0]], [['name' => 'conservation', 'passed' => '0']]] as $checks) {
            $result = app(ClientComputationContract::class)->receipt(['program' => ['hash' => str_repeat('a', 64)]],
                ['values' => ['x' => 1], 'checks' => $checks], null);
            $this->assertSame('unsupported', $result['status']);
            $this->assertNull($result['result']);
            $this->assertSame('invalid_input', $result['execution']['client_failure']);
        }
    }
}
