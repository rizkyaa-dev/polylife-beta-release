<?php

use App\Services\Ai\Contracts\LlmClientInterface;
use App\Services\Ai\DTOs\LlmMessage;
use App\Services\Ai\DTOs\LlmRequestOptions;
use App\Services\Ai\Enums\ThinkingEffort;
use App\Services\Ai\Science\AiScienceDelegation;
use App\Services\Ai\Science\Client\ClientComputationAgent;
use App\Services\Ai\Science\Client\ClientComputationContract;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$application = require dirname(__DIR__, 2).'/bootstrap/app.php';
$application->make(Kernel::class)->bootstrap();
config(['cache.default' => 'array', 'services.ai_science_kernel_enabled' => false]);
// Explicitly scoped fixture approval: no app/user data or guest host bindings.
$cases = [
    'arithmetic' => ['prompt' => 'Hitung (987654*321) + (2^20)/7. Laporkan hasil numerik dengan nama output value.', 'expected' => ['value' => 987654 * 321 + 2 ** 20 / 7]],
    'conversion' => ['prompt' => 'Konversi 72 km/jam ke m/s. Hitung dengan output value dan tampilkan satuannya.', 'expected' => ['value' => 20]],
    'linear' => ['prompt' => 'Selesaikan x+2y=5 dan 3x+4y=11. Output numerik bernama x dan y. Periksa residual kedua persamaan.', 'expected' => ['x' => 1, 'y' => 2]],
    'integral' => ['prompt' => 'Hitung integral exp(-x*x) dari 0 hingga 1 secara numerik, target galat absolut 1e-8. Output value. Jangan hardcode jawaban analitik.', 'expected' => ['value' => .7468241328124271]],
    'ode' => ['prompt' => 'RC ideal R=1 kiloohm C=100 mikrofarad, step Vs=10 V, V(0)=0. Integrasikan dV/dt=(Vs-V)/(R*C) sampai t=0.2 s secara numerik dengan target galat 1e-7 V. Output value untuk V(0.2). Jangan hardcode solusi analitik; gunakan solusi analitik hanya untuk pemeriksaan.', 'expected' => ['value' => 10 * (1 - exp(-2))]],
    'missing' => ['prompt' => 'Hitung suhu akhir setelah pemanasan cairan selama 30 detik. Tidak ada massa, kalor jenis, daya, suhu awal atau rugi panas yang diberikan. Jangan mengasumsikan nilainya.', 'statuses' => ['needs_clarification'], 'allow_direct_clarification' => true],
    'oversized' => ['prompt' => 'Selesaikan PDE–DAE stiff 100000 state dengan sparse Jacobian, implicit solver rtol 1e-10, continuation pseudo-arclength dan 20 eigenvalue. Semua data dianggap ada tetapi dilarang mereduksi sistem atau mengganti model. Jalankan seluruhnya dalam tool browser sekarang.', 'statuses' => ['unsupported']],
];
$selected = isset($argv[1]) ? explode(',', $argv[1]) : array_keys($cases);
if (in_array('reactor', $selected, true)) {
    $attachment = 'C:/Users/kikid/.codex/attachments/48220680-98cc-4af8-abf1-93932d13fa41/pasted-text.txt';
    $cases['reactor'] = ['prompt' => file_get_contents($attachment), 'expected_coverage' => 'partial'];
}
$client = app(LlmClientInterface::class);
$delegation = app(AiScienceDelegation::class);
$directory = storage_path('app/private/ai-science-browser-review-20260918');
if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
    throw new RuntimeException('Cannot create private report directory.');
}
foreach ($selected as $name) {
    if (! isset($cases[$name])) {
        throw new InvalidArgumentException('Unknown fixture.');
    }
    $fixture = $cases[$name];
    $started = microtime(true);
    $report = ['case' => $name, 'prompt' => $fixture['prompt'], 'scope' => 'Live main and client planner; browser guest engine executed in Node with fixture-only approval. No authenticated UI, HTTP, browser Worker or production records.', 'kernel_calls' => 0];
    echo $name.' started'.PHP_EOL;
    try {
        $options = new LlmRequestOptions(ThinkingEffort::Low, 90, 8192, hrtime(true) / 1e9 + 210);
        $system = 'You are the PolyLife assistant. Respond in Indonesian.'.$delegation->instruction();
        $history = [new LlmMessage('user', $fixture['prompt'])];
        $main = $client->chat($history, [$delegation->declaration()], $system, $options);
        $report['main_content'] = $main->content;
        $report['main_calls'] = array_map(fn ($call) => ['name' => $call->name, 'arguments' => $call->arguments], $main->toolCalls);
        if (! $main->isTruncated() && $main->toolCalls === [] && ($fixture['allow_direct_clarification'] ?? false)) {
            $report['direct_clarification'] = true;
            $report['final'] = $main->content;
            $report['oracle_passed'] = null;
            $report['semantic_review_required'] = true;
        } else {
            if ($main->isTruncated() || count($main->toolCalls) !== 1 || $main->toolCalls[0]->name !== AiScienceDelegation::TOOL_NAME) {
                throw new RuntimeException('Main did not produce exactly one complete science delegation.');
            }
            $call = $main->toolCalls[0];
            $request = $delegation->request($call->arguments, $fixture['prompt']);
            $report['plan'] = $plan = app(ClientComputationAgent::class)->planLocal($request, $options);
            echo $name.' plan='.$plan['status'].PHP_EOL;
            $result = $plan;
            if ($plan['status'] === 'ready') {
                $process = new Process(['node', __DIR__.'/client-program-probe.mjs']);
                $process->setInput(json_encode($plan['program'], JSON_THROW_ON_ERROR));
                $process->setTimeout(20);
                $process->mustRun();
                $report['guest'] = $guest = json_decode($process->getOutput(), true, 32, JSON_THROW_ON_ERROR);
                $report['receipt'] = $result = app(ClientComputationContract::class)->receipt($plan, $guest['result'] ?? null, $guest['failure'] ?? null);
            }
            $report['oracle_passed'] = isset($fixture['statuses'])
                ? in_array($plan['status'], $fixture['statuses'], true) && ! isset($plan['program'])
                : ($result['status'] ?? null) === 'client_computed';
            foreach ($fixture['expected'] ?? [] as $key => $expected) {
                $actual = $result['result']['values'][$key] ?? null;
                $report['oracle_passed'] = $report['oracle_passed'] && is_numeric($actual)
                    && abs((float) $actual - $expected) <= max(1e-7, abs($expected) * 1e-9);
            }
            if (isset($fixture['expected_coverage'])) {
                $report['oracle_passed'] = $report['oracle_passed'] && ($result['coverage'] ?? null) === $fixture['expected_coverage'];
                $report['semantic_review_required'] = true;
            }
            $history[] = new LlmMessage('assistant', $main->content, $main->toolCalls, reasoningContent: $main->reasoningContent);
            $history[] = new LlmMessage('tool', toolResult: ['call_id' => $call->id, 'tool_name' => $call->name, 'result' => $delegation->toolResult($result)]);
            $final = $client->chat($history, [], $system, $options);
            if ($final->isTruncated() || $final->toolCalls !== [] || trim($final->content) === '') {
                throw new RuntimeException('Final response incomplete.');
            }
            $report['final'] = $final->content;
        }
    } catch (Throwable $error) {
        $report['failure'] = ['class' => $error::class, 'message' => $error->getMessage()];
        $report['oracle_passed'] = false;
    }
    $report['duration_ms'] = (int) ((microtime(true) - $started) * 1000);
    $path = $directory.'/'.$name.'-'.bin2hex(random_bytes(6)).'.json';
    if (file_put_contents($path, json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
        throw new RuntimeException('Cannot persist report.');
    }
    echo json_encode(['case' => $name, 'passed' => $report['oracle_passed'], 'duration_ms' => $report['duration_ms'], 'evidence' => $path, 'failure' => $report['failure'] ?? null], JSON_THROW_ON_ERROR).PHP_EOL;
}
