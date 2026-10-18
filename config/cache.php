<?php
return [
    'default' => env('CACHE_DRIVER', 'file'),
    'stores' => [
        'array' => ['driver' => 'array'],
        'file' => ['driver' => 'file', 'path' => storage_path('framework/cache/data')],
        'database' => ['driver' => 'database', 'table' => 'cache', 'connection' => null],
    ],
    'prefix' => env('CACHE_PREFIX', 'meeting_ai_cache'),
];
