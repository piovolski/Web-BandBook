<?php

declare(strict_types=1);

$defaultDsn = 'sqlite:' . __DIR__ . '/storage/bandbook.sqlite';

return [
    'app_name' => getenv('APP_NAME') ?: 'BandBook',
    'timezone' => getenv('APP_TIMEZONE') ?: 'Europe/Warsaw',
    'session_name' => getenv('SESSION_NAME') ?: 'bandbook_session',
    'db_dsn' => getenv('DB_DSN') ?: $defaultDsn,
    'db_user' => getenv('DB_USER') ?: null,
    'db_password' => getenv('DB_PASSWORD') ?: null,
];
