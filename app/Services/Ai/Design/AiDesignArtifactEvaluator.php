<?php

namespace App\Services\Ai\Design;

use App\Services\Ai\AiCodeFenceParser;
use DOMDocument;
use DOMXPath;

/** Read-only, bounded checks. Never execute generated code or equate parsing with visual QA. */
final class AiDesignArtifactEvaluator
{
    public function __construct(private readonly AiCodeFenceParser $fences = new AiCodeFenceParser) {}

    public function evaluate(string $markdown): array
    {
        $report = ['scope' => 'static HTML only', 'render' => 'unverified', 'checks' => []];
        if (strlen($markdown) > 180000 || ! class_exists(DOMDocument::class)) {
            return $report + ['reason' => 'size limit or DOM parser unavailable'];
        }
        $parsed = $this->fences->parse($markdown);
        $blocks = array_values(array_filter($parsed['blocks'], fn (array $block) => in_array($block['language'], ['html', 'htm'], true)));
        if (! $parsed['complete'] || count($blocks) !== 1 || preg_match('/<!doctype\s+html|<html\b/i', $blocks[0]['source']) !== 1) {
            return $report + ['reason' => 'not a single HTML document'];
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            if (! $document->loadHTML('<?xml encoding="utf-8" ?>'.$blocks[0]['source'], LIBXML_NONET)) {
                return $report + ['reason' => 'HTML parser failed'];
            }
            $xpath = new DOMXPath($document);
            $ids = [];
            $duplicate = 0;
            foreach ($xpath->query('//*[@id]') as $node) {
                $id = $node->getAttribute('id');
                $duplicate += isset($ids[$id]) ? 1 : 0;
                $ids[$id] = true;
            }
            $broken = 0;
            $placeholder = 0;
            $namedAnchors = [];
            foreach ($xpath->query('//a[@name]') as $node) {
                $namedAnchors[$node->getAttribute('name')] = true;
            }
            foreach ($xpath->query('//a[@href]') as $node) {
                $href = trim($node->getAttribute('href'));
                if ($href === '' || $href === '#' || preg_match('/^javascript:/i', $href) === 1) {
                    $placeholder++;
                } elseif (str_starts_with($href, '#') && strtolower($href) !== '#top'
                    && ! isset($ids[rawurldecode(substr($href, 1))])
                    && ! isset($namedAnchors[rawurldecode(substr($href, 1))])) {
                    $broken++;
                }
            }
            $counts = ['duplicate_ids' => $duplicate, 'broken_fragment_links' => $broken,
                'placeholder_links' => $placeholder, 'images_without_alt' => $xpath->query('//img[not(@alt)]')->length];
            foreach ($counts as $check => $count) {
                $report['checks'][$check] = ['status' => $count === 0 ? 'pass' : 'fail', 'count' => $count];
            }

            return $report;
        } catch (\Throwable) {
            // Diagnostics must not make an otherwise usable coding response unavailable.
            return $report + ['reason' => 'static review unavailable'];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
