<?php

declare(strict_types=1);

$query = (string) ($_SERVER['QUERY_STRING'] ?? '');
header('Location: public/index.php' . ($query !== '' ? '?' . $query : ''));
