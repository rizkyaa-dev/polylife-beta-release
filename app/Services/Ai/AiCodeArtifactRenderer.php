<?php

namespace App\Services\Ai;

final class AiCodeArtifactRenderer
{
    /** @var array<string, string> */
    private const EXTENSIONS = [
        'html' => 'html', 'css' => 'css', 'javascript' => 'js', 'js' => 'js',
        'typescript' => 'ts', 'ts' => 'ts', 'php' => 'php', 'python' => 'py',
        'py' => 'py', 'java' => 'java', 'csharp' => 'cs', 'cs' => 'cs',
        'cpp' => 'cpp', 'c' => 'c', 'go' => 'go', 'rust' => 'rs', 'dart' => 'dart',
        'sql' => 'sql', 'bash' => 'sh', 'shell' => 'sh', 'json' => 'json',
        'xml' => 'xml', 'svg' => 'svg', 'markdown' => 'md', 'md' => 'md',
    ];

    public function decorate(string $html): string
    {
        return preg_replace_callback(
            '~<pre><code(?: class="language-([a-zA-Z0-9_+.#-]+)")?>(.*?)</code></pre>~s',
            fn (array $match): string => $this->artifact($match[1] ?? 'text', $match[2]),
            $html
        ) ?? $html;
    }

    private function artifact(string $rawLanguage, string $escapedCode): string
    {
        $language = $this->normalizeLanguage($rawLanguage);
        $extension = self::EXTENSIONS[$language] ?? 'txt';
        $filename = 'code.'.$extension;
        $runnable = $language === 'html';
        $label = htmlspecialchars($language, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<section class="ai-code-artifact" data-code-artifact data-code-language="'.$label.'" data-code-filename="'.$filename.'" data-code-runnable="'.($runnable ? 'true' : 'false').'">'
            .'<header class="ai-code-toolbar">'
            .'<span class="ai-code-language">'.$label.'</span>'
            .'<span class="ai-code-actions">'
            .'<button type="button" data-code-copy aria-label="Salin kode">Copy</button>'
            .'<button type="button" data-code-download aria-label="Download '.$filename.'">Download</button>'
            .($runnable ? '<button type="button" data-code-run aria-label="Jalankan preview kode">Run</button>' : '')
            .'</span></header>'
            .'<pre><code class="language-'.$label.'">'.$escapedCode.'</code></pre>'
            .'<span class="sr-only" data-code-status aria-live="polite"></span>'
            .'</section>';
    }

    private function normalizeLanguage(string $language): string
    {
        $language = strtolower(trim($language));

        return match ($language) {
            'js', 'node', 'nodejs' => 'javascript',
            'ts' => 'typescript',
            'py' => 'python',
            'cs', 'c#' => 'csharp',
            'c++' => 'cpp',
            'sh', 'shell', 'zsh' => 'bash',
            'htm' => 'html',
            default => array_key_exists($language, self::EXTENSIONS) ? $language : 'text',
        };
    }
}
