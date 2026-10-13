<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\Eco\EcoContextPolicy;
use Tests\TestCase;

class EcoContextPolicyTest extends TestCase
{
    public function test_configuration_is_bounded_before_becoming_an_immutable_snapshot(): void
    {
        config(['ai_eco.context_token_budget' => PHP_INT_MAX, 'ai_eco.recent_turns' => -1,
            'ai_eco.memory_token_budget' => 0, 'ai_eco.retrieval_message_limit' => PHP_INT_MAX]);
        $policy = EcoContextPolicy::configured();
        $this->assertSame(24000, $policy->tokenBudget);
        $this->assertSame(1, $policy->recentTurns);
        $this->assertSame(200, $policy->memoryTokenBudget);
        $this->assertSame(2000, $policy->retrievalMessageLimit);
        $this->assertEquals($policy, EcoContextPolicy::fromArray($policy->toArray()));
    }

    public function test_unknown_version_and_invalid_values_cannot_activate_eco(): void
    {
        $valid = (new EcoContextPolicy(6000, 2, 1200, 1000))->toArray();
        foreach ([null, [], 'invalid', 123, [...$valid, 'version' => 999], [...$valid, 'token_budget' => '6000'],
            [...$valid, 'recent_turns' => 100], [...$valid, 'retrieval_message_limit' => -1]] as $value) {
            $this->assertNull(EcoContextPolicy::fromArray($value));
        }
    }
}
