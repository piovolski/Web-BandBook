<?php

declare(strict_types=1);

use BandBook\Chord;
use BandBook\Database;
use BandBook\Repository;

require dirname(__DIR__) . '/src/Database.php';
require dirname(__DIR__) . '/src/Chord.php';

function now(): string
{
    return '2026-08-22T20:00:00+02:00';
}

function current_user(): ?array
{
    return ['id' => 1, 'display_name' => 'Tester'];
}

require dirname(__DIR__) . '/src/Repository.php';

function expectSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

expectSame('E H fis cis', Chord::transposeLine('D A e h', 2, 'pl', 'pl'), 'polska transpozycja z małymi molowymi');
expectSame('F#m Bm F#m Bm', Chord::transposeLine('fis h fis h', 0, 'pl', 'intl'), 'konwersja polska → międzynarodowa');
expectSame('G D G A (Bm)', Chord::transposeLine('G D G A (h)', 0, 'pl', 'intl'), 'akord opcjonalny w nawiasie');
expectSame('F Fm C', Chord::transposeLine('F f C', 0, 'pl', 'intl'), 'wielkość litery określa molowość');
expectSame('Dmaj7', Chord::transposeLine('Dmaj7', 0, 'intl', 'intl'), 'maj7 nie jest rozpoznawany jako moll');

$db = Database::connect([
    'db_dsn' => 'sqlite::memory:',
    'db_user' => null,
    'db_password' => null,
]);
$repo = new Repository($db);
$adminId = $repo->createAdmin('admin', 'Administrator', 'bardzo-dlugie-haslo', 'pl');
expectSame(true, $repo->hasUsers(), 'utworzenie administratora');
expectSame('admin', $repo->authenticate('admin', 'bardzo-dlugie-haslo')['username'] ?? null, 'logowanie');

$import = $repo->seedSongbook();
$songs = $repo->songs();
expectSame(889, $import['imported'], 'import pełnej biblioteki Google i OpenLP');
expectSame(889, count($songs), 'załadowanie 889 pozycji biblioteki');
$secondImport = $repo->seedSongbook();
expectSame(0, $secondImport['imported'], 'ponowny import nie tworzy duplikatów');
expectSame(889, $secondImport['skipped'], 'ponowny import pomija istniejące tytuły');

$eventId = $repo->saveEvent(null, [
    'name' => 'Próba testowa',
    'planned_at' => '2026-08-23T18:00',
    'location' => 'Sala',
    'status' => 'ready',
    'comment' => 'Test repertuaru',
]);
$eventSongId = $repo->addSongToEvent($eventId, (int) $songs[0]['id']);
$eventSong = $repo->eventSong($eventSongId);
expectSame(true, count($eventSong['form']) > 1, 'skopiowanie domyślnej formy do wydarzenia');

$snapshot = $repo->liveSnapshot($eventId, 'pl');
$firstFormId = (int) $snapshot['songs'][0]['form'][0]['id'];
$first = $repo->directLive($eventId, $firstFormId, $adminId);
expectSame('next', $first['action'], 'pierwsze kliknięcie ustawia następną część');
$second = $repo->directLive($eventId, $firstFormId, $adminId);
expectSame('now', $second['action'], 'drugie kliknięcie ustawia część graną teraz');

$repo->updateLiveSetting($eventId, 'song', $eventSongId, 'transpose_steps', 2);
$snapshot = $repo->liveSnapshot($eventId, 'intl');
expectSame($firstFormId, $snapshot['state']['current_form_id'], 'stan live pozostaje spójny po zmianie ustawień');
expectSame(2, $snapshot['songs'][0]['transpose_steps'], 'zmiana transpozycji zapisuje się w wydarzeniu');

echo "\nWszystkie testy zakończone powodzeniem.\n";
