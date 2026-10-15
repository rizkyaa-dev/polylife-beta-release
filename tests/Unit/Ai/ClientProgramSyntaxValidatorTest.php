<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\Science\Client\ClientProgramSyntaxValidator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class ClientProgramSyntaxValidatorTest extends TestCase
{
    public function test_parse_only_does_not_execute_host_side_effects_or_top_level_throw(): void
    {
        $sentinel = sys_get_temp_dir().DIRECTORY_SEPARATOR.'science-parse-'.Str::uuid();
        $source = 'require("node:fs").writeFileSync('.json_encode($sentinel).', "executed"); throw new Error("must not run"); function compute() {}';
        app(ClientProgramSyntaxValidator::class)->validate($source);
        $this->assertFileDoesNotExist($sentinel);
    }

    public function test_invalid_property_key_is_rejected_and_quoted_key_is_valid(): void
    {
        $validator = app(ClientProgramSyntaxValidator::class);
        $validator->validate('function compute(){return {"target_met_at_0.08":true}}');
        try {
            $validator->validate('function compute(){return {target_met_at_0.08:true}}');
            $this->fail('Invalid property keys must not reach browser execution.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('program.source', $error->errors());
            $this->assertStringNotContainsString('[stdin]', json_encode($error->errors()));
        }
    }

    public function test_unavailable_parser_is_infrastructure_failure_not_repairable_model_error(): void
    {
        config(['services.ai_science_syntax_node_binary' => 'nonexistent-science-parser-'.Str::uuid()]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('syntax checker is unavailable');
        app(ClientProgramSyntaxValidator::class)->validate('function compute(){}');
    }

    public function test_expired_turn_deadline_does_not_start_the_parser(): void
    {
        config(['services.ai_science_syntax_node_binary' => 'nonexistent-parser']);
        $this->expectException(AiProviderException::class);
        app(ClientProgramSyntaxValidator::class)->validate('function compute(){}', hrtime(true) / 1e9 - 1);
    }
}
