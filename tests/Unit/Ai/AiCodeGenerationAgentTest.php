<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\AiCodeGenerationAgent;
use App\Services\Ai\AiCodingInstructionRouter;
use App\Services\Ai\AiCodingPromptBuilder;
use App\Services\Ai\Contracts\LlmClientInterface;
use App\Services\Ai\Design\AiDesignIntentResolver;
use App\Services\Ai\DTOs\AiCodingBrief;
use App\Services\Ai\DTOs\AiDesignIntent;
use App\Services\Ai\DTOs\LlmRequestOptions;
use App\Services\Ai\DTOs\LlmResponse;
use App\Services\Ai\Exceptions\AiProviderException;
use Tests\TestCase;

class AiCodeGenerationAgentTest extends TestCase
{
    public function test_coder_receives_one_untrusted_envelope_without_tools_or_brief_in_system(): void
    {
        $client = new class implements LlmClientInterface
        {
            public array $request = [];

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                $this->request = compact('messages', 'tools', 'systemInstruction');

                return new LlmResponse("```python\nprint('ok')\n```");
            }
        };
        $brief = new AiCodingBrief('python', 'cli', ['main.py'], ['USER_ONLY_MARKER'], ['Works'], [], false);
        $agent = new AiCodeGenerationAgent($client, app(AiCodingPromptBuilder::class), app(AiDesignIntentResolver::class));
        $agent->generate('Latest follow-up', $brief, (new AiCodingInstructionRouter)->forLanguage('python'), new LlmRequestOptions, 'SOURCE_ONLY_MARKER');
        $this->assertSame([], $client->request['tools']);
        $this->assertCount(1, $client->request['messages']);
        $this->assertStringContainsString('USER_ONLY_MARKER', $client->request['messages'][0]->content);
        $this->assertStringContainsString('SOURCE_ONLY_MARKER', $client->request['messages'][0]->content);
        $this->assertStringNotContainsString('USER_ONLY_MARKER', $client->request['systemInstruction']);
        $this->assertStringNotContainsString('SOURCE_ONLY_MARKER', $client->request['systemInstruction']);
        $this->assertStringContainsString('[REVISI]', $client->request['systemInstruction']);
        $this->assertStringNotContainsString('[UI WEB]', $client->request['systemInstruction']);
    }

    public function test_mismatched_language_policy_is_rejected_before_inference(): void
    {
        $client = $this->createMock(LlmClientInterface::class);
        $client->expects($this->never())->method('chat');
        $agent = new AiCodeGenerationAgent($client, app(AiCodingPromptBuilder::class), app(AiDesignIntentResolver::class));
        $brief = new AiCodingBrief('python', 'cli', ['main.py'], ['Print'], ['Works'], [], false);
        $this->expectException(AiProviderException::class);
        $agent->generate('Print', $brief, (new AiCodingInstructionRouter)->forLanguage('html'), new LlmRequestOptions);
    }

    public function test_unselected_palette_does_not_force_grey_but_selected_palette_is_preserved(): void
    {
        $client = new class implements LlmClientInterface
        {
            public array $payloads = [];

            public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
            {
                $content = $messages[0]->content;
                $this->payloads[] = json_decode(substr($content, strpos($content, '{'), strrpos($content, '}') - strpos($content, '{') + 1), true, 32, JSON_THROW_ON_ERROR);

                return new LlmResponse("```html\n<html></html>\n```");
            }
        };
        $agent = new AiCodeGenerationAgent($client, app(AiCodingPromptBuilder::class), app(AiDesignIntentResolver::class));
        foreach ([[], ['color_family' => 'cool'], ['accent_hex' => '#bb4400']] as $intent) {
            $brief = new AiCodingBrief('html', 'browser', ['index.html'], ['Page'], ['Works'], [], true, AiDesignIntent::fromArray($intent));
            $agent->generate('Make page', $brief, (new AiCodingInstructionRouter)->forLanguage('html'), new LlmRequestOptions);
        }
        $this->assertArrayNotHasKey('colors', $client->payloads[0]['design_plan']['tokens']);
        $this->assertFalse($client->payloads[0]['design_plan']['tokens']['palette_selected']);
        $this->assertSame('#245c99', $client->payloads[1]['design_plan']['tokens']['colors']['accent']);
        $this->assertSame('#bb4400', $client->payloads[2]['design_plan']['tokens']['colors']['accent']);
        $this->assertArrayNotHasKey('verification', $client->payloads[1]['design_plan']['tokens']);
    }
}
