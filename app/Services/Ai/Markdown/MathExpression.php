<?php

namespace App\Services\Ai\Markdown;

use League\CommonMark\Node\Inline\AbstractInline;

final class MathExpression extends AbstractInline
{
    public function __construct(public readonly string $source, public readonly bool $display)
    {
        parent::__construct();
    }
}
