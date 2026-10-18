<?php
return [
    'default' => env('QUEUE_CONNECTION', 'database'),
    'connections' => [
        'sync' => ['driver' => 'sync'],
        'database' => ['driver' => 'database', 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 4200, 'after_commit' => true],
    ],
    'failed' => ['driver' => 'database-uuids', 'database' => env('DB_CONNECTION', 'sqlite'), 'table' => 'failed_jobs'],
];
