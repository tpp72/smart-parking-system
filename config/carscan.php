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

    /*
    |--------------------------------------------------------------------------
    | Accuracy Threshold
    |--------------------------------------------------------------------------
    | ผล AI ผ่านเกณฑ์เมื่อ Accuracy "มากกว่า" ค่านี้ (project-plan.md §10.3 — > 85%)
    */
    'accuracy_threshold' => 85,

    /*
    |--------------------------------------------------------------------------
    | SSL Verification
    |--------------------------------------------------------------------------
    | ตรวจสอบใบรับรอง SSL ของ AI API — เปิดไว้เสมอใน Production
    | ปิดได้เฉพาะเครื่อง dev ที่ยังไม่ได้ตั้งค่า CA bundle: CARSCAN_VERIFY_SSL=false
    */
    'verify_ssl' => env('CARSCAN_VERIFY_SSL', true),

    /*
    |--------------------------------------------------------------------------
    | Fake Mode (E2E / Demo)
    |--------------------------------------------------------------------------
    | ไม่เรียก Claude API — อ่านผลจากชื่อไฟล์รูป "ทะเบียน__จังหวัด__ยี่ห้อ__สี__Accuracy.png"
    | ทำงานเฉพาะ APP_ENV=local หรือ testing เท่านั้น (ดู CarScanService::fakeEnabled)
    */
    'fake' => env('CARSCAN_FAKE', false),
];
