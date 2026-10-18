<?php
return [
    'driver' => env('SESSION_DRIVER', 'file'), 'lifetime' => 480,
    'expire_on_close' => false, 'encrypt' => false,
    'files' => storage_path('framework/sessions'), 'connection' => null,
    'table' => 'sessions', 'store' => null, 'lottery' => [2, 100],
    'cookie' => env('SESSION_COOKIE', 'meeting_ai_session'), 'path' => '/', 'domain' => env('SESSION_DOMAIN'),
    'secure' => env('SESSION_SECURE_COOKIE'), 'http_only' => true, 'same_site' => 'lax',
];
