<?php

namespace App\Services\Ai\Markdown;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

final class MathExtension implements ExtensionInterface, NodeRendererInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addInlineParser(new MathInlineParser, 250);
        $environment->addRenderer(MathExpression::class, $this);
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): HtmlElement
    {
        MathExpression::assertInstanceOf($node);

        $attributes = [
            'class' => $node->display ? 'ai-math ai-math-display' : 'ai-math',
            'data-ai-math' => $node->display ? 'display' : 'inline',
        ];
        if ($node->display) {
            $attributes['tabindex'] = '0';
            $attributes['aria-label'] = 'Rumus matematika';
        }

        return new HtmlElement('span', $attributes, htmlspecialchars($node->source, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }
}
