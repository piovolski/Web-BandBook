<?php

declare(strict_types=1);

return [
    'app_name' => 'BandBook',
    'timezone' => 'Europe/Warsaw',
    'session_name' => 'bandbook_session',
    // Najprostszy wariant: SQLite. Katalog storage musi być zapisywalny przez PHP.
    'db_dsn' => 'sqlite:' . __DIR__ . '/storage/bandbook.sqlite',
    'db_user' => null,
    'db_password' => null,
];
