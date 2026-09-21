<?php

use App\Services\Ai\Contracts\LlmClientInterface;
use App\Services\Ai\DTOs\LlmMessage;
use App\Services\Ai\DTOs\LlmRequestOptions;
use App\Services\Ai\Enums\ThinkingEffort;
use App\Services\Ai\Science\AiScienceAgent;
use App\Services\Ai\Science\AiScienceDelegation;
use App\Services\Ai\Science\Client\ClientComputationAgent;
use Illuminate\Contracts\Console\Kernel;

// Manual paid-provider observation; no production users, conversations, flags or DB writes.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$application = require dirname(__DIR__, 2).'/bootstrap/app.php';
$application->make(Kernel::class)->bootstrap();
config(['cache.default' => 'array']);
$source = file_get_contents($argv[1] ?? '');
if ($source === false || ! mb_check_encoding($source, 'UTF-8') || mb_strlen($source) > 8000) {
    throw new InvalidArgumentException('Supply the explicit UTF-8 reactor attachment, at most 8000 characters.');
}
$report = ['source' => $source, 'source_sha256' => hash('sha256', $source),
    'scope' => 'Live main -> registered science planner -> optional dynamic planner -> main narration. No browser, HTTP, user-specific instruction builder or production state writes.',
    'oracle_scope' => 'Full reactor task must refuse or clarify, not fabricate numerical outputs. Final prose is inspected separately.'];
$started = microtime(true);
$options = new LlmRequestOptions(ThinkingEffort::Low, 90, 8192, hrtime(true) / 1e9 + 240);
$client = app(LlmClientInterface::class);
$delegation = app(AiScienceDelegation::class);
try {
    $history = [new LlmMessage('user', $source)];
    $system = 'You are the PolyLife science assistant. Respond in Indonesian. Preserve the user task, quantities and scope.'.$delegation->instruction();
    $stage = microtime(true);
    $main = $client->chat($history, [$delegation->declaration()], $system, $options);
    if ($main->isTruncated()) {
        throw new RuntimeException('Initial provider response was truncated.');
    }
    $report['main_ms'] = (int) ((microtime(true) - $stage) * 1000);
    $report['initial_content'] = $main->content;
    $report['main_calls'] = array_map(fn ($call) => ['name' => $call->name, 'arguments' => $call->arguments], $main->toolCalls);
    $call = $main->toolCalls[0] ?? null;
    if (count($main->toolCalls) > 1 || ($call !== null && $call->name !== AiScienceDelegation::TOOL_NAME)) {
        throw new RuntimeException('Expected at most one science call.');
    }
    $request = $delegation->request($call?->arguments ?? ['problem' => 'Assess feasibility of all requested reactor tasks in original_problem. Preserve the full model and requested outputs.'], $source);
    $report['brief'] = $request;
    $stage = microtime(true);
    $kernel = app(AiScienceAgent::class)->plan($request, $options);
    $report['kernel_ms'] = (int) ((microtime(true) - $stage) * 1000);
    $report['kernel'] = $kernel;
    $result = $kernel;
    if ($kernel['status'] === 'unsupported') {
        $stage = microtime(true);
        $result = app(ClientComputationAgent::class)->plan($request, $kernel, $options);
        $report['dynamic_ms'] = (int) ((microtime(true) - $stage) * 1000);
        $report['dynamic'] = $result;
    }
    // This full task has essential missing data and unsupported algorithms. Never
    // auto-approve generated scripts or silently execute an auxiliary replacement.
    $report['execution_boundary_passed'] = in_array($result['status'], ['needs_clarification', 'unsupported'], true)
        && ! isset($result['program']) && ! isset($result['result']);
    if ($call !== null) {
        $history[] = new LlmMessage('assistant', $main->content, $main->toolCalls, reasoningContent: $main->reasoningContent);
        $history[] = new LlmMessage('tool', toolResult: ['call_id' => $call->id, 'tool_name' => $call->name, 'result' => $delegation->toolResult($result)]);
        $stage = microtime(true);
        $final = $client->chat($history, [], $system, $options);
        if ($final->isTruncated() || $final->toolCalls !== [] || trim($final->content) === '') {
            throw new RuntimeException('Final response was truncated, empty or requested an unavailable tool.');
        }
        $report['final_ms'] = (int) ((microtime(true) - $stage) * 1000);
        $report['final'] = $final->content;
    } else {
        $report['main_skipped_delegation'] = true;
        $report['final'] = $main->content;
    }
} catch (Throwable $error) {
    $report['failure'] = ['class' => $error::class, 'message' => $error->getMessage()];
    $report['execution_boundary_passed'] = false;
}
$report['duration_ms'] = (int) ((microtime(true) - $started) * 1000);
$directory = storage_path('app/private/ai-science-reactor-review-20260918');
if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
    throw new RuntimeException('Cannot create private evidence directory.');
}
$path = $directory.'/reactor-'.bin2hex(random_bytes(6)).'.json';
if (file_put_contents($path, json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
    throw new RuntimeException('Cannot save evidence.');
}
echo json_encode(['evidence' => $path, 'boundary_passed' => $report['execution_boundary_passed'], 'failure' => $report['failure'] ?? null, 'duration_ms' => $report['duration_ms']], JSON_THROW_ON_ERROR).PHP_EOL;
exit($report['execution_boundary_passed'] ? 0 : 1);
