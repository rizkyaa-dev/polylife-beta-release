<?php

namespace Tests\Support;

use App\Models\AiChatRun;
use App\Services\Ai\Contracts\LlmClientInterface;
use App\Services\Ai\DTOs\LlmRequestOptions;
use App\Services\Ai\DTOs\LlmResponse;
use App\Services\Ai\DTOs\LlmToolCall;
use Illuminate\Support\Facades\DB;

/** Deterministic inference fixture; the real HTTP, queue, broker and UI stay enabled. */
final class ScienceE2eClient implements LlmClientInterface
{
    public function chat(array $messages, array $tools = [], ?string $systemInstruction = null, ?LlmRequestOptions $options = null): LlmResponse
    {
        ($options?->ensureActive)?->__invoke();
        $stage = str_contains($systemInstruction ?? '', 'isolated scientific modeling agent') ? 'planner'
            : (str_starts_with($messages[0]->content ?? '', '<delegated_coding_request>') ? 'coder' : 'main');
        $run = AiChatRun::where('status', 'running')->latest('id')->firstOrFail();
        DB::table('science_e2e_calls')->insert(['stage' => $stage, 'run_id' => $run->id, 'effort' => $options?->thinkingEffort?->value]);
        if ($stage === 'planner') {
            $envelope = json_decode($messages[0]->content, true, flags: JSON_THROW_ON_ERROR);
            $problem = $envelope['request']['problem'];
            $fixtures = json_decode(file_get_contents(__DIR__.'/../Fixtures/science-kernel.json'), true, flags: JSON_THROW_ON_ERROR);
            $name = str_contains($problem, 'kerja') ? 'work-integral' : (str_contains($problem, 'pegas') ? 'spring-equilibrium' : 'damped-oscillator');
            $fixture = collect($fixtures)->firstWhere('name', $name);
            $inputs = $fixture['inputs'];
            if ($name === 'damped-oscillator') {
                $inputs['dimensions'] = ['t' => [0, 0, 1, 0, 0, 0, 0],
                    'states' => [[0, 1, 0, 0, 0, 0, 0], [0, 1, -1, 0, 0, 0, 0]]];
                $inputs['derivatives'][1]['args'][0]['args'][0]['dimension'] = [0, 0, -2, 0, 0, 0, 0];
                $inputs['derivatives'][1]['args'][1]['args'][0]['dimension'] = [0, 0, -1, 0, 0, 0, 0];
            }

            return new LlmResponse(json_encode(['status' => 'ready', 'model' => $name,
                'assumptions' => ['Ideal specified model; physical formulation is unverified.'],
                'units' => ['Normalized SI'], 'solver' => $fixture['solver'], 'inputs' => $inputs], JSON_THROW_ON_ERROR));
        }
        if ($stage === 'coder') {
            $content = $messages[0]->content;
            $payload = json_decode(substr($content, strpos($content, "\n") + 1, strrpos($content, "\n") - strpos($content, "\n") - 1), true, flags: JSON_THROW_ON_ERROR);
            if (($payload['science_contract']['solver'] ?? null) !== 'ode_ivp'
                || ($payload['science_contract']['reference_result']['status'] ?? null) !== 'computed') {
                throw new \RuntimeException('Cross-domain coder did not receive the authoritative science contract.');
            }

            return new LlmResponse('```html'."\n".file_get_contents(__DIR__.'/../Fixtures/science-oscillator-calculator.html')."\n```");
        }
        $prompt = collect($messages)->last(fn ($message) => $message->role === 'user')->content;
        $science = collect($messages)->last(fn ($message) => ($message->toolResult['tool_name'] ?? null) === 'delegate_science_problem');
        if (! $science) {
            return new LlmResponse(null, [new LlmToolCall('science-e2e-'.$run->id, 'delegate_science_problem', ['problem' => $prompt])]);
        }
        $result = $science->toolResult['result'];
        if (str_contains($prompt, 'kalkulator')) {
            return new LlmResponse(null, [new LlmToolCall('coder-e2e-'.$run->id, 'delegate_code_generation', [
                'language' => 'html', 'runtime' => 'browser', 'files' => ['index.html'], 'runnable' => true,
                'science_step_id' => $result['science_step_id'],
                'requirements' => ['Standalone oscillator calculator from the referenced contract. Adjustable time, displacement and velocity in SI.',
                    'Disclose model assumptions and verification limitations. No external dependencies.'],
                'acceptance_criteria' => ['Default t=5 agrees with the reference; changing time updates both state outputs.'],
            ])]);
        }
        $computed = $result['result'];
        $numbers = $computed['final_state'] ?? $computed['solution'] ?? [$computed['value']];

        return new LlmResponse('Hasil sains: '.implode(', ', array_map(fn ($number) => sprintf('%.10f', $number), $numbers)).'. Model fisika belum terverifikasi.');
    }
}
