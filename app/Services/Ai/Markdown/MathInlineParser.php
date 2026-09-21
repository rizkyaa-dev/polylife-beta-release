<?php

namespace App\Services\Ai\Markdown;

use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

final class MathInlineParser implements InlineParserInterface
{
    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::oneOf('$$', '$', '\\(', '\\[');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $cursor = $inlineContext->getCursor();
        $open = $inlineContext->getFullMatch();
        $close = match ($open) {
            '\\(' => '\\)', '\\[' => '\\]', default => $open
        };
        $display = in_array($open, ['$$', '\\['], true);
        $remaining = $cursor->getRemainder();
        $offset = strlen($open);

        // Only examine a bounded expression; code spans/blocks are owned by CommonMark.
        while (($end = strpos($remaining, $close, $offset)) !== false && $end <= 8192) {
            $slashes = 0;
            for ($i = $end - 1; $i >= 0 && $remaining[$i] === '\\'; $i--) {
                $slashes++;
            }
            if ($slashes % 2 === 0) {
                $source = substr($remaining, strlen($open), $end - strlen($open));
                if (trim($source) === '' || (! $display && str_contains($source, "\n"))) {
                    return false;
                }
                // Avoid interpreting ordinary prices such as "$10 and $20" as math.
                if ($open === '$' && (ctype_space($source[0]) || ctype_space(substr($source, -1)) || preg_match('/^\d+(?:[.,]\d+)?(?:\s|$)/', $source))) {
                    return false;
                }
                $cursor->advanceBy(mb_strlen(substr($remaining, 0, $end + strlen($close))));
                $inlineContext->getContainer()->appendChild(new MathExpression($source, $display));

                return true;
            }
            $offset = $end + strlen($close);
        }

        return false;
    }
}
