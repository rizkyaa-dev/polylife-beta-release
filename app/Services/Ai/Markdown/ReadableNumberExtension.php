<?php

namespace App\Services\Ai\Markdown;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Extension\Table\TableCell;
use League\CommonMark\Node\Inline\Text;

/** Display large prose quantities without rewriting code, math sources or stored Markdown. */
final class ReadableNumberExtension implements ExtensionInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addEventListener(DocumentParsedEvent::class, $this->formatDocument(...));
    }

    private function formatDocument(DocumentParsedEvent $event): void
    {
        $walker = $event->getDocument()->walker();
        while ($step = $walker->next()) {
            $node = $step->getNode();
            if (! $step->isEntering() || ! $node instanceof Text) {
                continue;
            }
            $inTable = false;
            for ($parent = $node->parent(); $parent !== null; $parent = $parent->parent()) {
                $inTable = $inTable || $parent instanceof TableCell;
                if ($parent instanceof Link) {
                    continue 2;
                }
            }

            $literal = $node->getLiteral();
            // Unfenced JSON/arrays and bare URLs are technical text, not display quantities.
            if (strpbrk($literal, '{}[]') !== false || preg_match('~\b[a-z][a-z0-9+.-]*://~i', $literal)) {
                continue;
            }

            // Five digits avoids changing years. Boundaries exclude grouped values,
            // identifiers, scientific notation, dates, paths and serial numbers.
            $formatted = preg_replace_callback(
                '/(?<![\p{L}\p{N}_.,\/:#-])(?<sign>[+\-\x{2212}]?)(?<integer>[1-9][0-9]{4,})(?:[.,](?<fraction>[0-9]+))?(?![\p{L}\p{N}_\/:#-]|[.,][0-9])/u',
                static function (array $match): string {
                    // Group strings, never floats: keep every digit and trailing decimal zero.
                    $integer = strrev(implode('.', str_split(strrev($match['integer']), 3)));

                    return $match['sign'].$integer.(isset($match['fraction']) ? ','.$match['fraction'] : '');
                },
                $literal
            );
            $formatted ??= $literal;
            // Scientific table quantities use decimal dots in legacy model output.
            // Outside tables require an explicit unit, avoiding versions and bare identifiers.
            $unitPattern = '\s*(?:mg|kg|g|mL|L|m\x{00b3}|m\x{00b2}|menit|detik|s|K|\x{00b0}C|Pa|bar|%)(?:\b|$|[\x{207b}\x{00b9}\x{00b2}\x{00b3}\x{00b7}/])';
            $unitBoundary = $inTable ? '' : '(?='.$unitPattern.')';
            $formatted = preg_replace_callback(
                '~(?<![\p{L}\p{N}_.,/:#-])([+\-\x{2212}]?(?:0|[1-9][0-9]{0,3}))\.([0-9]+)(?![\p{L}\p{N}_/:#-]|[.,][0-9])'.$unitBoundary.'~u',
                static function (array $match) use ($formatted, $unitPattern): string {
                    $integer = $match[1][0];
                    $fraction = $match[2][0];
                    $suffix = substr($formatted, $match[0][1] + strlen($match[0][0]));
                    // A single dot and three digits can be an already grouped monetary value.
                    if (strlen($fraction) === 3 && ! preg_match('/^[+\-\x{2212}]?0$/u', $integer)
                        && ! preg_match('~^'.$unitPattern.'~u', $suffix)) {
                        return $match[0][0];
                    }

                    return $integer.','.$fraction;
                },
                $formatted,
                flags: PREG_OFFSET_CAPTURE
            ) ?? $formatted;
            $node->setLiteral($formatted);
        }
    }
}
