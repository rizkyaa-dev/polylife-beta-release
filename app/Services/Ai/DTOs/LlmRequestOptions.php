<?php

namespace App\Services\Ai\DTOs;

use App\Services\Ai\Enums\ThinkingEffort;
use Closure;

final class LlmRequestOptions
{
    public function __construct(
        public readonly ThinkingEffort $thinkingEffort = ThinkingEffort::High,
        public readonly ?int $timeoutSeconds = null,
        public readonly ?int $maxOutputTokens = null,
        public readonly ?float $deadlineAt = null,
        public readonly ?Closure $ensureActive = null,
        public readonly ?Closure $usageObserver = null
    ) {}

    public function forAttempt(int $timeoutSeconds, float $deadlineAt): self
    {
        return new self($this->thinkingEffort, $timeoutSeconds, $this->maxOutputTokens, $deadlineAt, $this->ensureActive, $this->usageObserver);
    }

    public function withGuard(Closure $ensureActive): self
    {
        return new self($this->thinkingEffort, $this->timeoutSeconds, $this->maxOutputTokens, $this->deadlineAt, $ensureActive, $this->usageObserver);
    }

    public function withUsageObserver(Closure $observer): self
    {
        return new self($this->thinkingEffort, $this->timeoutSeconds, $this->maxOutputTokens, $this->deadlineAt, $this->ensureActive, $observer);
    }
}
