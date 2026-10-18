<?php
return [
    'default' => env('DB_CONNECTION', 'sqlite'),
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite', 'url' => env('DATABASE_URL'),
            'database' => (static function () {
                $database = env('DB_DATABASE', 'database/database.sqlite');
                if ($database === ':memory:') return $database;
                if (str_starts_with($database, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $database)) return $database;
                return base_path($database);
            })(),
            'prefix' => '', 'foreign_key_constraints' => true,
        ],
        'pgsql' => [
            'driver' => 'pgsql', 'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'), 'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'meeting_ai'), 'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', ''), 'charset' => 'utf8', 'prefix' => '',
            'prefix_indexes' => true, 'search_path' => 'public', 'sslmode' => 'prefer',
        ],
        'mysql' => [
            'driver' => 'mysql', 'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'), 'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'meeting_ai'), 'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''), 'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '',
            'prefix_indexes' => true, 'strict' => true, 'engine' => null,
        ],
    ],
    'migrations' => 'migrations',
    'redis' => ['client' => env('REDIS_CLIENT', 'phpredis'), 'default' => ['host' => env('REDIS_HOST', '127.0.0.1'), 'password' => env('REDIS_PASSWORD'), 'port' => env('REDIS_PORT', '6379'), 'database' => env('REDIS_DB', '0')]],
];
