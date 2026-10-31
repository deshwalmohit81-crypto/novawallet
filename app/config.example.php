<?php
declare(strict_types=1);

return [
    'database' => [
        'host' => 'localhost',
        'name' => 'novawallet',
        'user' => 'novawallet_user',
        'password' => 'replace-with-a-unique-database-password',
        'charset' => 'utf8mb4',
    ],
    // Generate with: php -r "echo password_hash('your-long-admin-password', PASSWORD_DEFAULT), PHP_EOL;"
    'admin_password_hash' => 'replace-with-a-password_hash-output',
    // Set this to a long random value; do not reuse an account password.
    'app_key' => 'replace-with-at-least-32-random-characters',
    'base_path' => '',
];
