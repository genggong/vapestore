<?php

return [
    'host' => getenv('VAPEKONG_DB_HOST') ?: 'localhost',
    'database' => getenv('VAPEKONG_DB_NAME') ?: 'vapekong_pos',
    'username' => getenv('VAPEKONG_DB_USER') ?: 'vapekong_user',
    'password' => getenv('VAPEKONG_DB_PASS') ?: 'change_me',
    'charset' => 'utf8mb4',
];
