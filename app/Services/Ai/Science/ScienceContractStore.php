<?php

namespace App\Services\Ai\Science;

use App\Models\AiChatRunStep;
use App\Services\Ai\Science\Models\ModelCanonicalJson;
use App\Services\Ai\Science\Models\ScienceModelRegistry;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Branch-scoped references, not planner-provided copies of scientific results. */
final class ScienceContractStore
{
    public function __construct(private readonly ScienceModelRegistry $models) {}

    public function ancestors(Collection $lineage): Collection
    {
        $ids = $lineage->filter(fn ($message) => $message->role === 'assistant' && $message->status === 'completed')->pluck('id');
        if ($ids->isEmpty()) {
            return collect();
        }

        return AiChatRunStep::query()->where('tool_name', AiScienceDelegation::TOOL_NAME)->where('status', 'completed')
            ->whereHas('run', fn ($query) => $query->whereIn('assistant_message_id', $ids))
            ->latest('id')->limit(8)->get()->filter(fn ($step) => $this->isUsable($step))
            ->take(4)->keyBy('id');
    }

    public function resolve(int $id, Collection $available): array
    {
        $step = $available->get($id);
        $payload = $step?->private_payload;
        if (! $this->isUsable($step)) {
            throw ValidationException::withMessages(['science_step_id' => 'A computed scientific contract is not available on this branch.']);
        }
        $prepared = $this->models->prepare($payload);
        if ($payload['domain_model'] ?? null) {
            if (ModelCanonicalJson::encode($prepared['solver_inputs']) !== ModelCanonicalJson::encode($payload['solver_inputs'])) {
                throw ValidationException::withMessages(['science_step_id' => 'Domain equations changed; recompute this scientific reference before using it for coding.']);
            }
        }

        return ['step_id' => $id, 'solver' => $payload['solver'], 'inputs' => $payload['solver_inputs'],
            'model' => $payload['model'], 'assumptions' => $payload['assumptions'], 'units' => $payload['units'],
            'reference_result' => $payload['result'], 'model_verification' => $prepared['model_verification'],
            'version' => $payload['version'], 'domain_model' => $payload['domain_model'] ?? null,
            'model_evidence' => $prepared['model_evidence']];
    }

    private function isUsable(?AiChatRunStep $step): bool
    {
        if (! $step || $step->tool_name !== AiScienceDelegation::TOOL_NAME || $step->status !== 'completed') {
            return false;
        }
        $payload = $step->private_payload;
        $verification = $payload['result']['verification']['status'] ?? null;

        return is_array($payload) && ($payload['status'] ?? null) === 'ready'
            && ($payload['result']['status'] ?? null) === 'computed' && is_array($payload['solver_inputs'] ?? null)
            && ($verification !== 'bracketed_interval_checked'
                || ($payload['result']['kernel_version'] ?? null) === ScienceSolverRegistry::KERNEL_VERSION)
            && in_array($verification,
                ['numerically_checked', 'estimated_error_only', 'estimated_local_error_only', 'bracketed_interval_checked'], true);
    }
}
