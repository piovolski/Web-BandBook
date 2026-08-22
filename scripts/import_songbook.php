<?php

declare(strict_types=1);

use BandBook\Repository;

require dirname(__DIR__) . '/src/bootstrap.php';

$repo = new Repository($db);

try {
    $result = $repo->seedSongbook();
    $total = count($repo->songs(true));
    fwrite(STDOUT, "Import Śpiewnika guanelliańskiego zakończony.\n");
    fwrite(STDOUT, "Dodano: {$result['imported']}\n");
    fwrite(STDOUT, "Pominięto istniejące: {$result['skipped']}\n");
    fwrite(STDOUT, "Pozycji w bibliotece: {$total}\n");
} catch (Throwable $error) {
    fwrite(STDERR, "Import nie powiódł się: {$error->getMessage()}\n");
    exit(1);
}
