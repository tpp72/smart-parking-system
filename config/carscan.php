<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Google Gemini API Key
    |--------------------------------------------------------------------------
    | Used by CarScanService to call Gemini Vision API for car image analysis.
    | Set via .env: GEMINI_API_KEY=AIza...
    */
    'gemini_api_key' => env('GEMINI_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Gemini Model
    |--------------------------------------------------------------------------
    */
    'model' => env('CARSCAN_MODEL', 'gemini-2.0-flash'),
];
