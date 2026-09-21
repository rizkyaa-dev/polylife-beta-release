<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\AiScienceExecution;
use App\Services\Ai\Science\ScienceExecutionBroker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AiScienceExecutionController extends Controller
{
    public function __construct(private readonly ScienceExecutionBroker $broker) {}

    public function claim(Request $request, int $execution): JsonResponse
    {
        $ticket = $this->owned($request, $execution);
        $validated = $request->validate(['client_id' => ['required', 'uuid']]);

        return response()->json($this->broker->claim($ticket, $validated['client_id']));
    }

    public function submit(Request $request, int $execution): JsonResponse
    {
        $ticket = $this->owned($request, $execution);
        abort_if(strlen($request->getContent()) > 32768, 413, 'Scientific result exceeds its resource limit.');
        $validated = $request->validate(['attempt' => ['required', 'integer', 'min:1'], 'token' => ['required', 'string', 'size:48'],
            'result' => ['required_without:failure', Rule::prohibitedIf($request->exists('failure')), 'array'],
            'failure' => ['required_without:result', Rule::prohibitedIf($request->exists('result')), 'in:timeout,unavailable,invalid_input,execution_failed,syntax_error,version_mismatch,cancelled']]);
        $validated['attempt'] = (int) $validated['attempt'];
        $this->broker->submit($ticket, $validated);

        return response()->json(['status' => 'accepted'], 202);
    }

    private function owned(Request $request, int $id): AiScienceExecution
    {
        return AiScienceExecution::query()->whereKey($id)
            ->whereHas('run.session', fn ($query) => $query->where('user_id', $request->user()->id))->firstOrFail();
    }
}
