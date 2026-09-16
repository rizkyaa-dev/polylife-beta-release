<?php

use App\Services\Ai\AiCodeGenerationAgent;
use App\Services\Ai\AiCodingDelegation;
use App\Services\Ai\AiCodingInstructionRouter;
use App\Services\Ai\Contracts\LlmClientInterface;
use App\Services\Ai\DTOs\LlmMessage;
use App\Services\Ai\DTOs\LlmRequestOptions;
use App\Services\Ai\Enums\ThinkingEffort;
use App\Services\Ai\Exceptions\AiProviderException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

// Manual, paid provider probe. Does not create users, conversations or workspace records.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$application = require dirname(__DIR__, 2).'/bootstrap/app.php';
$application->make(Kernel::class)->bootstrap();

config(['cache.default' => 'array', 'services.ai_provider_retries' => 0]);
$case = $argv[1] ?? 'portfolio';
if ($case === '--check') {
    echo json_encode(['ready' => true], JSON_THROW_ON_ERROR);
    exit(0);
}
$prompt = match ($case) {
    'portfolio' => 'Bikinin HTML standalone untuk web portfolio saya sebagai software engineer. Fokus bukti proyek dan kontak. Konten pribadi belum ada, gunakan placeholder yang jujur. Bisa dibuka offline, tanpa library eksternal.',
    'coffee' => 'Buat HTML standalone untuk coffee shop Kopi Senja. Tampilkan identitas, menu kopi beserta harga contoh berlabel, dan kontak. Tampilan harus cocok untuk usaha kopi, responsif dan bisa dibuka offline tanpa library eksternal. Jangan membuat order/checkout atau testimonial.',
    default => throw new InvalidArgumentException('Unknown visual probe case.'),
};
// Diagnostic budget only; production inference deadlines remain unchanged.
$options = new LlmRequestOptions(ThinkingEffort::Low, 75, 16384);
$delegation = app(AiCodingDelegation::class);
$started = microtime(true);
try {
    if (isset($argv[2])) {
        $arguments = json_decode(file_get_contents($argv[2]), true, 32, JSON_THROW_ON_ERROR);
    } else {
        $main = app(LlmClientInterface::class)->chat(
            [new LlmMessage('user', $prompt)], [$delegation->declaration()], $delegation->instruction(), $options
        );
        $call = $main->toolCalls[0] ?? null;
        if ($call === null || $call->name !== AiCodingDelegation::TOOL_NAME) {
            throw new RuntimeException('Main model did not delegate this implementation request.');
        }
        $arguments = $call->arguments;
    }
    $brief = $delegation->brief($arguments, $prompt, new AiCodingInstructionRouter);
    $coderStarted = microtime(true);
    $response = app(AiCodeGenerationAgent::class)->generate(
        $prompt, $brief, (new AiCodingInstructionRouter)->forLanguage($brief->language), $options
    );
    echo json_encode(['case' => $case, 'prompt' => $prompt, 'arguments' => $arguments,
        'content' => $response->content, 'finish' => $response->finishReason,
        'coder_seconds' => round(microtime(true) - $coderStarted, 3),
        'total_seconds' => round(microtime(true) - $started, 3)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (AiProviderException $exception) {
    echo json_encode(['case' => $case, 'error' => $exception->errorCode], JSON_THROW_ON_ERROR);
    exit(2);
} catch (ValidationException $exception) {
    echo json_encode(['case' => $case, 'validation_errors' => $exception->errors()], JSON_THROW_ON_ERROR);
    exit(3);
}
