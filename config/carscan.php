<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Anthropic API Key
    |--------------------------------------------------------------------------
    | Used by CarScanService to call Claude Vision API for car image analysis.
    | Set via .env: ANTHROPIC_API_KEY=sk-ant-...
    */
    'anthropic_api_key' => env('ANTHROPIC_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Claude Model
    |--------------------------------------------------------------------------
    | Recommended: claude-haiku-4-5 (fast + cheap), claude-opus-4-8 (best accuracy)
    */
    'model' => env('CARSCAN_MODEL', 'claude-opus-4-8'),
];
