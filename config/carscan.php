<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Python Binary
    |--------------------------------------------------------------------------
    | Path to the python executable. On Windows try 'python', on Linux 'python3'.
    | Override via .env: CARSCAN_PYTHON_BIN=python3
    */
    'python_bin' => env('CARSCAN_PYTHON_BIN', 'python'),
];
