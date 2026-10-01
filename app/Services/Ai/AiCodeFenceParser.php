<?php

namespace App\Services\Ai;

final class AiCodeFenceParser
{
    /** @return array{blocks: list<array{fence: string, language: string, source: string}>, complete: bool} */
    public function parse(string $source): array
    {
        $blocks = [];
        $open = null;
        foreach (preg_split('/\R/u', $source) ?: [] as $line) {
            if ($open === null) {
                if (preg_match('/^ {0,3}(`{3,}|~{3,})([^`~]*)$/', $line, $match)) {
                    $open = ['fence' => $match[1], 'language' => strtolower(trim($match[2])), 'source' => ''];
                }
            } elseif (preg_match('/^ {0,3}'.preg_quote($open['fence'][0], '/').'{'.strlen($open['fence']).',}[ \t]*$/', $line)) {
                $blocks[] = $open;
                $open = null;
            } else {
                $open['source'] .= $line."\n";
            }
        }

        return ['blocks' => $blocks, 'complete' => $open === null];
    }
}
