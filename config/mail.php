<?php
return [
    'default' => env('MAIL_MAILER', 'smtp'),
    'mailers' => [
        'smtp' => [
            'transport' => 'smtp', 'host' => env('MAIL_HOST', 'localhost'),
            'port' => (int) env('MAIL_PORT', 465), 'encryption' => env('MAIL_ENCRYPTION', 'ssl'),
            'username' => env('MAIL_USERNAME'), 'password' => env('MAIL_PASSWORD'),
            'timeout' => 30, 'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ],
        'log' => ['transport' => 'log'],
        'array' => ['transport' => 'array'],
    ],
    'from' => ['address' => env('MAIL_FROM_ADDRESS', 'ai@ingco.co.id'), 'name' => env('MAIL_FROM_NAME', 'INGCO Meeting AI')],
];
