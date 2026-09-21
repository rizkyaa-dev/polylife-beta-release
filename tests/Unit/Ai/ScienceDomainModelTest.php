<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\Contracts\LlmClientInterface;
use App\Services\Ai\DTOs\LlmRequestOptions;
use App\Services\Ai\DTOs\LlmResponse;
use App\Services\Ai\Science\AiScienceAgent;
use App\Services\Ai\Science\AiScienceDelegation;
use App\Services\Ai\Science\BoundedExpression;
use App\Services\Ai\Science\Models\ScienceModelRegistry;
use App\Services\Ai\Science\ScienceSolverRegistry;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ScienceDomainModelTest extends TestCase
{
    private function fixture(): array
    {
        return json_decode(file_get_contents(base_path('tests/Fixtures/science-rc-domain.json')), true, 32, JSON_THROW_ON_ERROR);
    }

    private function plan(): array
    {
        $fixture = $this->fixture();

        return ['status' => 'ready', 'solver' => 'ode_ivp', 'domain_model' => $fixture['domain_model'],
            'model_source' => $fixture['source'], 'model_verification' => 'physically_verified',
            'model_evidence' => ['status' => 'everything_verified'], 'solver_inputs' => []];
    }

    public function test_rc_builder_matches_independent_oracle_and_never_claims_physical_truth(): void
    {
        $plan = app(ScienceModelRegistry::class)->prepare($this->plan());
        app(ScienceSolverRegistry::class)->validateContract($plan['solver'], $plan['solver_inputs']);
        $result = app(ScienceSolverRegistry::class)->solve($plan['solver'], $plan['solver_inputs']);
        $this->assertEqualsWithDelta(8.324986336363539, $result['final_state'][0], 1e-7);
        $this->assertEqualsWithDelta(4.979720827625142, $result['final_state'][1], 1e-7);
        $this->assertSame('declared_conversions_checked', $result['conversion_verification']['status']);
        $this->assertSame('declared_dimensions_checked', $result['dimension_verification']['status']);
        $this->assertSame('unverified', $plan['model_verification']);
        $this->assertSame('declared_structure_checked', $plan['model_evidence']['status']);
        $this->assertSame(['n1', 'n2'], $plan['model_evidence']['state_order']);
        $checks = array_column($plan['model_evidence']['checks'], 'status', 'name');
        $this->assertSame('checked', $checks['kcl_equation_construction']);
        $this->assertSame('unverified', $checks['text_interpretation_and_completeness']);
        $this->assertSame('assumed', $checks['physical_idealizations']);
        $this->assertSame('unverified', $checks['numerical_method_applicability']);
        $this->assertLessThanOrEqual(32, count($plan['solver_inputs']['conversions']));
        $this->assertStringNotContainsString('_source', json_encode($plan['solver_inputs']));
    }

    public function test_injected_wrong_equations_and_cached_evidence_are_replaced_on_completion(): void
    {
        $plan = $this->plan();
        $plan['solver_inputs'] = ['derivatives' => [['op' => 'const', 'value' => 9999]], 'initial' => [123]];
        $agent = app(AiScienceAgent::class);
        $result = $agent->complete($plan, new LlmRequestOptions);
        $this->assertSame('unverified', $result['model_verification']);
        $this->assertEqualsWithDelta(8.324986336363539, $result['result']['final_state'][0], 1e-7);
        $firstEvidence = $result['model_evidence'];
        $result['model_evidence'] = ['status' => 'client_verified'];
        $result['solver_inputs'] = [];
        $replay = $agent->complete($result, new LlmRequestOptions);
        $this->assertSame($firstEvidence, $replay['model_evidence']);
        $this->assertSame($result['result'], $replay['result']);
    }

    public function test_passive_resistor_orientation_does_not_change_physics(): void
    {
        $a = $this->plan();
        $b = $a;
        foreach ($b['domain_model']['components'] as &$component) {
            [$component['from'], $component['to']] = [$component['to'], $component['from']];
        }
        unset($component);
        $registry = app(ScienceModelRegistry::class);
        $this->assertSame($registry->prepare($a)['solver_inputs'], $registry->prepare($b)['solver_inputs']);
    }

    public function test_kcl_and_passivity_hold_across_deterministic_state_probes(): void
    {
        $plan = app(ScienceModelRegistry::class)->prepare($this->plan());
        $expressions = app(BoundedExpression::class);
        $f1 = $expressions->compile($plan['solver_inputs']['derivatives'][0], ['t', 'y0', 'y1']);
        $f2 = $expressions->compile($plan['solver_inputs']['derivatives'][1], ['t', 'y0', 'y1']);
        foreach ([[0, 0], [1, -2], [10, 5], [-3, 7]] as [$v1, $v2]) {
            $bindings = ['t' => 0, 'y0' => $v1, 'y1' => $v2];
            $dv1 = $f1($bindings);
            $dv2 = $f2($bindings);
            $this->assertEqualsWithDelta((10 - $v1) / 1000 - ($v1 - $v2) / 2000, 1e-4 * $dv1, 1e-12);
            $this->assertEqualsWithDelta(($v1 - $v2) / 2000 - $v2 / 3000, 2.2e-4 * $dv2, 1e-12);
            // Stored-energy derivative = source power minus positive resistor losses.
            $sourcePower = 10 * (10 - $v1) / 1000;
            $loss = (10 - $v1) ** 2 / 1000 + ($v1 - $v2) ** 2 / 2000 + $v2 ** 2 / 3000;
            $this->assertEqualsWithDelta($sourcePower - $loss, 1e-4 * $v1 * $dv1 + 2.2e-4 * $v2 * $dv2, 1e-12);
        }
    }

    public function test_state_order_and_selected_outputs_are_explicit(): void
    {
        $plan = $this->plan();
        [$plan['domain_model']['nodes'][2], $plan['domain_model']['nodes'][3]] = [$plan['domain_model']['nodes'][3], $plan['domain_model']['nodes'][2]];
        $plan['domain_model']['outputs'] = ['n1'];
        $prepared = app(ScienceModelRegistry::class)->prepare($plan);
        $result = app(ScienceSolverRegistry::class)->solve($prepared['solver'], $prepared['solver_inputs']);
        $this->assertSame(['n2', 'n1'], $prepared['model_evidence']['state_order']);
        $this->assertCount(1, $result['outputs']);
        $this->assertSame('n1', $result['outputs'][0]['name']);
        $this->assertEqualsWithDelta(8.324986336363539, $result['outputs'][0]['value'], 1e-7);
    }

    public function test_unsupported_domains_cannot_receive_evidence_but_generic_path_still_works(): void
    {
        $generic = ['status' => 'ready', 'solver' => 'linear_system', 'solver_inputs' => ['matrix' => [[1]], 'rhs' => [2]],
            'model_verification' => 'verified', 'model_evidence' => ['status' => 'checked']];
        $prepared = app(ScienceModelRegistry::class)->prepare($generic);
        $this->assertSame('unverified', $prepared['model_evidence']['status']);
        $this->assertSame('unverified', $prepared['model_verification']);
        $this->assertSame($generic['solver_inputs'], $prepared['solver_inputs']);
        $this->assertSame(2.0, app(AiScienceAgent::class)->complete($generic, new LlmRequestOptions)['result']['solution'][0]);
        $plan = $this->plan();
        $plan['domain_model']['domain'] = 'arbitrary_plugin_class';
        $this->expectException(ValidationException::class);
        app(ScienceModelRegistry::class)->prepare($plan);
    }

    public function test_initial_interpretations_remain_visible_not_verified_source_semantics(): void
    {
        $plan = $this->plan();
        $plan['model_source'] .= ' Initially uncharged.';
        $plan['domain_model']['nodes'][2]['initial']['quote'] = 'Initially uncharged';
        $plan['domain_model']['t_start']['quote'] = 'Initially uncharged';
        $prepared = app(ScienceModelRegistry::class)->prepare($plan);
        $check = collect($prepared['model_evidence']['checks'])->firstWhere('name', 'source_quantity_binding');
        $this->assertSame(['uncharged_initial_interpretation', 'zero_time_origin_assumed'], array_column($check['interpretations'], 'binding'));
        $this->assertSame('unverified', $prepared['model_verification']);
    }

    #[DataProvider('invalidMutations')]
    public function test_invalid_or_injected_domain_models_fail_closed(string $mutation): void
    {
        $plan = $this->plan();
        $model = &$plan['domain_model'];
        switch ($mutation) {
            case 'negative_resistor': $model['components'][0]['value']['value'] = -1;
                break;
            case 'wrong_source_number': $model['components'][0]['value']['value'] = 2;
                break;
            case 'wrong_unit': $model['components'][0]['value']['unit'] = 's';
                break;
            case 'fake_quote': $model['components'][0]['quote'] = 'An invented connection';
                break;
            case 'unknown_node': $model['components'][0]['to'] = 'unknown';
                break;
            case 'self_loop': $model['components'][0]['to'] = 'src';
                break;
            case 'missing_capacitor': array_pop($model['components']);
                break;
            case 'coupling_capacitor': $model['components'][3]['to'] = 'n2';
                break;
            case 'duplicate_node': $model['nodes'][] = $model['nodes'][2];
                break;
            case 'duplicate_component': $model['components'][] = $model['components'][0];
                break;
            case 'second_ground': $model['nodes'][] = ['id' => 'ground2', 'kind' => 'ground'];
                break;
            case 'unsupported_device': $model['components'][0]['type'] = 'inductor';
                break;
            case 'bad_outputs': $model['outputs'] = ['src'];
                break;
            case 'duplicate_outputs': $model['outputs'] = ['n1', 'n1'];
                break;
            case 'unknown_field': $model['execute'] = 'arbitrary code';
                break;
            case 'unknown_version': $model['version'] = 'future-model';
                break;
            case 'wrong_solver': $plan['solver'] = 'integrate';
                break;
            case 'missing_source': unset($plan['model_source']);
                break;
            case 'bad_shape': $model['nodes'] = 'invalid';
                break;
            case 'too_many_nodes': $model['nodes'] = array_fill(0, 10, $model['nodes'][0]);
                break;
        }
        $this->expectException(ValidationException::class);
        app(ScienceModelRegistry::class)->prepare($plan);
    }

    public static function invalidMutations(): array
    {
        return array_map(fn ($name) => [$name], ['negative_resistor', 'wrong_source_number', 'wrong_unit', 'fake_quote',
            'unknown_node', 'self_loop', 'missing_capacitor', 'coupling_capacitor', 'duplicate_node', 'duplicate_component',
            'second_ground', 'unsupported_device', 'bad_outputs', 'duplicate_outputs', 'unknown_field', 'unknown_version',
            'wrong_solver', 'missing_source', 'bad_shape', 'too_many_nodes']);
    }

    public function test_real_agent_prepares_domain_json_without_a_proposed_ast_and_rebuilds_on_resume(): void
    {
        $fixture = $this->fixture();
        $client = new class($fixture) implements LlmClientInterface
        {
            public int $calls = 0;

            public function __construct(private readonly array $fixture) {}

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                $this->calls++;

                return new LlmResponse(json_encode(['status' => 'ready', 'model' => 'Declared ideal RC network.',
                    'assumptions' => [], 'units' => ['V'], 'solver' => 'ode_ivp',
                    'domain_model' => $this->fixture['domain_model'], 'model_verification' => 'physically_verified']));
            }
        };
        $this->app->instance(LlmClientInterface::class, $client);
        $agent = app(AiScienceAgent::class);
        $plan = $agent->plan(['problem' => 'Incorrect delegated interpretation', 'original_problem' => $fixture['source']], new LlmRequestOptions);
        $this->assertNull($plan['result']);
        $this->assertSame(1, $plan['planning_attempts']);
        $this->assertSame('declared_structure_checked', $plan['model_evidence']['status']);
        $this->assertSame($fixture['source'], $plan['model_source']);
        $complete = $agent->complete($plan, new LlmRequestOptions);
        $this->assertEqualsWithDelta(8.324986336363539, $complete['result']['final_state'][0], 1e-7);
        $this->assertSame(1, $client->calls);
    }

    public function test_model_fingerprint_binds_source_and_validator_while_public_result_omits_raw_source(): void
    {
        $plan = $this->plan();
        $registry = app(ScienceModelRegistry::class);
        $prepared = $registry->prepare($plan);
        $plan['model_source'] .= ' Additional context not claimed as understood.';
        $other = $registry->prepare($plan);
        $this->assertNotSame($prepared['model_evidence']['fingerprint'], $other['model_evidence']['fingerprint']);
        $public = app(AiScienceDelegation::class)->toolResult($prepared);
        $this->assertArrayNotHasKey('model_source', $public);
        $this->assertSame($prepared['model_evidence'], $public['model_evidence']);
        $this->assertArrayHasKey('model_source', $prepared);
    }

    public function test_nonready_plans_cannot_retain_cached_results_or_claim_execution_authority(): void
    {
        $plan = $this->plan();
        $plan['status'] = 'needs_clarification';
        $plan['result'] = ['status' => 'computed', 'value' => 999];
        $prepared = app(ScienceModelRegistry::class)->prepare($plan);
        $this->assertNull($prepared['result']);
        $this->assertNull($prepared['solver_inputs']);
        $this->assertNull($prepared['solver']);
        $this->assertNull($prepared['domain_model']);
        $this->assertSame('unverified', $prepared['model_evidence']['status']);
    }

    #[DataProvider('unsupportedSourceNotation')]
    public function test_compound_source_notation_is_not_mistaken_for_a_literal_voltage(string $quote): void
    {
        $plan = $this->plan();
        $plan['model_source'] .= ' '.$quote;
        $plan['domain_model']['nodes'][1]['voltage'] = ['value' => 2, 'unit' => 'V', 'quote' => $quote];
        $this->expectException(ValidationException::class);
        app(ScienceModelRegistry::class)->prepare($plan);
    }

    public function test_model_identity_is_stable_after_json_roundtrip_and_object_key_reordering(): void
    {
        $registry = app(ScienceModelRegistry::class);
        $plan = $this->plan();
        $a = $registry->prepare($plan);
        $plan['domain_model'] = array_reverse($plan['domain_model'], true);
        $plan['domain_model']['nodes'][1]['voltage']['value'] = 10.0;
        $plan = json_decode(json_encode($plan, JSON_THROW_ON_ERROR), true, 32, JSON_THROW_ON_ERROR);
        $b = $registry->prepare($plan);
        $this->assertSame($a['model_evidence']['fingerprint'], $b['model_evidence']['fingerprint']);
    }

    public static function unsupportedSourceNotation(): array
    {
        return [['1/2 V'], ['10-2 V'], ['2 V/s'], ['2 V^2'], ['−2 V'], ['10−2 V'], ['±2 V']];
    }

    public function test_unicode_negative_voltage_keeps_its_sign(): void
    {
        $plan = $this->plan();
        $plan['model_source'] .= ' Source is −2 V.';
        $plan['domain_model']['nodes'][1]['voltage'] = ['value' => -2, 'unit' => 'V', 'quote' => '−2 V'];
        $prepared = app(ScienceModelRegistry::class)->prepare($plan);
        $result = app(ScienceSolverRegistry::class)->solve($prepared['solver'], $prepared['solver_inputs']);
        $this->assertLessThan(0, $result['final_state'][0]);
        $this->assertEqualsWithDelta(-2 / 10 * 8.324986336363539, $result['final_state'][0], 1e-7);
    }
}
