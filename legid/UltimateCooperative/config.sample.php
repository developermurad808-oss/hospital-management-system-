<?php
return [
    'app' => [
        'name' => 'UltimateCooperative HMS',
        'url' => 'http://localhost/UltimateCooperative',
        'timezone' => 'Africa/Lagos',
        'installed' => false,
        'jwt_secret' => 'change-this-during-installation',
        'upload_path' => __DIR__ . '/uploads',
    ],
    'database' => [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => '3306',
        'name' => 'ultimate_hms',
        'user' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
];
