<?php

namespace App\Services\Ai\Benchmark;

final readonly class AiBenchmarkScenario
{
    /**
     * @param  list<string>  $forbiddenTools
     * @param  list<string>  $forbiddenReplyFragments
     * @param  list<string>  $requiredTools
     */
    public function __construct(
        public string $id,
        public string $prompt,
        public ?string $expectedTool = null,
        public array $forbiddenTools = [],
        public array $forbiddenReplyFragments = [],
        public bool $confirmProposal = false,
        public array $requiredTools = [],
    ) {}
}
