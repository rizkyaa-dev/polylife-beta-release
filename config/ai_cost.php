<?php

return [
    'enabled' => (bool) env('AI_COST_DISPLAY_ENABLED', true),
    'models' => ['deepseek-flash', 'deepseek-v4-flash', 'deepseek-v4-flash-vision-exp'],

    // Reference estimate: peak pricing, with every input token treated as a cache miss.
    // https://api-docs.deepseek.com/quick_start/pricing/
    'profile' => [
        'model' => 'DeepSeek 4.1 Flash',
        'input_usd_per_million' => 0.30,
        'output_usd_per_million' => 1.20,
        'usd_to_idr' => (float) env('AI_COST_USD_TO_IDR', 17937),
        // JISDOR reference; update the rate and date together, without a request-time lookup.
        'exchange_rate_date' => env('AI_COST_EXCHANGE_RATE_DATE', '2026-10-01'),
    ],
];
