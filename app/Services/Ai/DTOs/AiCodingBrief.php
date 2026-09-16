<?php

namespace App\Services\Ai\DTOs;

final class AiCodingBrief
{
    /**
     * @param  list<string>  $files
     * @param  list<string>  $requirements
     * @param  list<string>  $acceptanceCriteria
     * @param  array<string, string>  $visualDirection
     */
    public function __construct(
        public readonly string $language,
        public readonly string $runtime,
        public readonly array $files,
        public readonly array $requirements,
        public readonly array $acceptanceCriteria,
        public readonly array $visualDirection,
        public readonly bool $runnable,
        public readonly ?AiDesignIntent $designIntent = null
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $brief = [
            'language' => $this->language,
            'runtime' => $this->runtime,
            'files' => $this->files,
            'requirements' => $this->requirements,
            'acceptance_criteria' => $this->acceptanceCriteria,
            'visual_direction' => $this->visualDirection,
            'execution' => ['runnable' => $this->runnable],
        ];
        if ($this->designIntent !== null) {
            $brief['design_intent'] = $this->designIntent->toArray();
        }

        return $brief;
    }
}
