<?php

namespace App\Services\Ai;

use App\Services\Ai\Design\AiDesignArtifactEvaluator;
use App\Services\Ai\DTOs\AiCodingBrief;
use App\Services\Ai\DTOs\LlmResponse;

/** Structural acceptance only; generated code is never executed on the server. */
final class AiCodingArtifactValidator
{
    public function __construct(private readonly AiDesignArtifactEvaluator $designs, private readonly AiCodeFenceParser $fences = new AiCodeFenceParser) {}

    public function errors(LlmResponse $response, AiCodingBrief $brief): array
    {
        $source = $response->content ?? '';
        if ($response->hasToolCalls() || blank($source) || strlen($source) > 180000) {
            return ['artifact' => 'Return bounded source files without tool calls.'];
        }
        $parsed = $this->fences->parse($source);
        $blocks = $parsed['blocks'];
        if (! $parsed['complete'] || $blocks === [] || count($blocks) !== count($brief->files)) {
            return ['files' => 'Return one complete, closed, language-labelled fenced block per requested file.'];
        }
        $languages = ['htm' => 'html', 'js' => 'javascript', 'node' => 'javascript', 'nodejs' => 'javascript', 'ts' => 'typescript', 'py' => 'python',
            'sh' => 'bash', 'shell' => 'bash', 'zsh' => 'bash', 'cs' => 'csharp', 'c#' => 'csharp', 'c++' => 'cpp', 'rs' => 'rust', 'md' => 'markdown', 'txt' => 'text'];
        $extensions = ['html', 'css', 'javascript', 'typescript', 'php', 'python', 'java', 'csharp', 'cpp', 'c', 'go', 'rust', 'dart', 'sql', 'bash', 'json', 'xml', 'svg', 'markdown', 'text'];
        foreach ($blocks as $index => $block) {
            if ($block['language'] === '' || blank($block['source'])) {
                return ['files' => 'Each source file needs a language label and nonempty contents.'];
            }
            $extension = strtolower(pathinfo($brief->files[$index], PATHINFO_EXTENSION));
            $expectedLanguage = $languages[$extension] ?? $extension;
            if (! in_array($expectedLanguage, $extensions, true)) {
                // Extensionless/config files may legitimately use another language.
                // Do not infer their syntax from the project's primary language.
                $expectedLanguage = null;
            }
            $actualLanguage = $languages[$block['language']] ?? $block['language'];
            if ($expectedLanguage !== null && $actualLanguage !== $expectedLanguage) {
                return ['language' => 'Use the language belonging to each requested filename, in brief order.'];
            }
            if (in_array($block['language'], ['html', 'htm'], true)
                && preg_match('/<!doctype\s+html|<html\b/i', $block['source'])
                && ! preg_match('/<\/html\s*>/i', $block['source'])) {
                return ['html' => 'Complete the HTML document envelope.'];
            }
        }
        $report = $this->designs->evaluate($source);
        $errors = [];
        foreach ($report['checks'] ?? [] as $name => $check) {
            if ($check['status'] === 'fail') {
                $errors[$name] = 'Correct the detected static HTML defect (count: '.$check['count'].').';
            }
        }

        return $errors;
    }
}
