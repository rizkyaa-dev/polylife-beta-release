<?php

namespace App\Services\Ai\Design;

use DOMDocument;
use DOMXPath;

/** Read-only, bounded checks. Never execute generated code or equate parsing with visual QA. */
final class AiDesignArtifactEvaluator
{
    public function evaluate(string $markdown): array
    {
        $report = ['scope' => 'static HTML only', 'render' => 'unverified', 'checks' => []];
        if (strlen($markdown) > 180000 || ! class_exists(DOMDocument::class)) {
            return $report + ['reason' => 'size limit or DOM parser unavailable'];
        }
        preg_match_all('/```(?:html|htm)[^\r\n]*\R(.*?)```/is', $markdown, $blocks);
        if (count($blocks[1]) !== 1 || preg_match('/<!doctype\s+html|<html\b/i', $blocks[1][0]) !== 1) {
            return $report + ['reason' => 'not a single HTML document'];
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            if (! $document->loadHTML('<?xml encoding="utf-8" ?>'.$blocks[1][0], LIBXML_NONET)) {
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
            foreach ($xpath->query('//a[@href]') as $node) {
                $href = trim($node->getAttribute('href'));
                if ($href === '' || $href === '#' || preg_match('/^javascript:/i', $href) === 1) {
                    $placeholder++;
                } elseif (str_starts_with($href, '#') && ! isset($ids[rawurldecode(substr($href, 1))])) {
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
