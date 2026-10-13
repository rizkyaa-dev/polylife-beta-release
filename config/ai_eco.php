<?php

return [
    'enabled' => (bool) env('AI_ECO_ENABLED', true),
    // Soft planning targets: exact task inputs and provider transcripts are never cut.
    'context_token_budget' => (int) env('AI_ECO_CONTEXT_TOKEN_BUDGET', 6000),
    'recent_turns' => (int) env('AI_ECO_RECENT_TURNS', 2),
    'memory_token_budget' => (int) env('AI_ECO_MEMORY_TOKEN_BUDGET', 1200),
    'retrieval_message_limit' => (int) env('AI_ECO_RETRIEVAL_MESSAGE_LIMIT', 1000),
];
