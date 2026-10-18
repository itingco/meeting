<?php
return [
    'default' => env('LOG_CHANNEL', 'stack'),
    'channels' => [
        'stack' => ['driver' => 'stack', 'channels' => ['single'], 'ignore_exceptions' => false],
        'single' => ['driver' => 'single', 'path' => storage_path('logs/laravel.log'), 'level' => env('LOG_LEVEL', 'debug')],
        'stderr' => ['driver' => 'monolog', 'handler' => Monolog\Handler\StreamHandler::class, 'with' => ['stream' => 'php://stderr']],
    ],
];
