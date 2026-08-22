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

$categories = $repo->categories();
expectSame(17, count($categories), 'utworzenie działów, źródeł i śpiewników');
$sectionCategory = array_values(array_filter(
    $categories,
    fn (array $category): bool => $category['name'] === 'Śpiewem radujmy innych'
))[0] ?? null;
expectSame(143, (int) ($sectionCategory['song_count'] ?? 0), 'odtworzenie liczebności działu guanelliańskiego');
$browserSongs = $repo->songBrowser(0);
expectSame(889, count($browserSongs), 'przeglądarka obejmuje pełną bibliotekę');
$sunrise = array_values(array_filter(
    $browserSongs,
    fn (array $song): bool => $song['title'] === 'Każdy wschód słońca'
))[0] ?? null;
expectSame(true, (bool) ($sunrise['has_chords'] ?? false), 'filtr dostępności chwytów');
expectSame('Śpiewem radujmy innych', $sunrise['categories'][0]['name'] ?? null, 'przypisanie pieśni do oryginalnego działu');
$sunrisePreview = $repo->songPreview((int) ($sunrise['id'] ?? 0));
expectSame(11, count($sunrisePreview['form'] ?? []), 'podgląd zachowuje pełną domyślną formę');

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

$editedForm = array_map(
    fn (array $item, int $index): array => [
        'sectionId' => (int) $item['section_id'],
        'transpose' => (int) $item['transpose_steps'],
        'comment' => $item['comment'] ?? '',
        'labelOverride' => $index === 0 ? 'Zwrotka próbna' : null,
        'lyricsOverride' => $index === 0 ? "Tekst tylko dla wydarzenia\nDrugi wers" : null,
        'chordsOverride' => $index === 0 ? "C G\na F" : null,
    ],
    $eventSong['form'],
    array_keys($eventSong['form'])
);
$repo->saveEventSong($eventSongId, [
    'transpose_steps' => 0,
    'bpm_override' => '',
    'comment' => '',
    'form_json' => json_encode($editedForm, JSON_THROW_ON_ERROR),
]);
$eventSong = $repo->eventSong($eventSongId);
expectSame('Zwrotka próbna', $eventSong['form'][0]['section_label'], 'edycja nazwy części w repertuarze');
expectSame("Tekst tylko dla wydarzenia\nDrugi wers", $eventSong['form'][0]['lyrics'], 'edycja tekstu części w repertuarze');
expectSame("C G\na F", $eventSong['form'][0]['chords'], 'edycja chwytów części w repertuarze');

$secondEventId = $repo->saveEvent(null, [
    'name' => 'Drugie wydarzenie',
    'planned_at' => '2026-08-24T18:00',
    'location' => 'Sala',
    'status' => 'draft',
    'comment' => '',
]);
$secondEventSongId = $repo->addSongToEvent($secondEventId, (int) $eventSong['song_id']);
$secondEventSong = $repo->eventSong($secondEventSongId);
expectSame($eventSong['form'][0]['source_lyrics'], $secondEventSong['form'][0]['lyrics'], 'nadpisanie nie zmienia tej samej pieśni w innym wydarzeniu');

$snapshot = $repo->liveSnapshot($eventId, 'pl');
$firstFormId = (int) $snapshot['songs'][0]['form'][0]['id'];
expectSame('Zwrotka próbna', $snapshot['songs'][0]['form'][0]['label'], 'nadpisana część trafia do widoku live');
$first = $repo->directLive($eventId, $firstFormId, $adminId);
expectSame('next', $first['action'], 'pierwsze kliknięcie ustawia następną część');
$second = $repo->directLive($eventId, $firstFormId, $adminId);
expectSame('now', $second['action'], 'drugie kliknięcie ustawia część graną teraz');

$repo->updateLivePartContent($eventId, $firstFormId, [
    'label' => 'Refren z Live',
    'lyrics' => "Zmiana podczas grania\nWidoczna dla zespołu",
    'chords' => "D A\nh G",
]);
$snapshot = $repo->liveSnapshot($eventId, 'pl');
expectSame('Refren z Live', $snapshot['songs'][0]['form'][0]['label'], 'edycja nazwy części z live');
expectSame("Zmiana podczas grania\nWidoczna dla zespołu", $snapshot['songs'][0]['form'][0]['lyrics'], 'edycja tekstu części z live');
expectSame("D A\nh G", $snapshot['songs'][0]['form'][0]['editable_chords'], 'live zachowuje chwyty w tonacji bazowej');

$secondRevisionBefore = (int) $repo->liveSnapshot($secondEventId, 'pl')['revision'];
$repo->updateLivePartContent($eventId, $firstFormId, [
    'label' => 'Refren zapisany w źródle',
    'lyrics' => "Tekst źródłowy z Live\nDla wszystkich wydarzeń",
    'chords' => "E H\ncis A",
    'save_to_source' => true,
]);
$eventSong = $repo->eventSong($eventSongId);
expectSame(null, $eventSong['form'][0]['label_override'], 'zapis źródłowy usuwa nadpisanie bieżącej części');
expectSame('Refren zapisany w źródle', $eventSong['form'][0]['section_label'], 'bieżące wydarzenie dziedziczy zapisane źródło');
$sourceSong = $repo->song((int) $eventSong['song_id']);
$sourceSection = array_values(array_filter(
    $sourceSong['sections'],
    fn (array $section): bool => (int) $section['id'] === (int) $eventSong['form'][0]['section_id']
))[0] ?? null;
expectSame("Tekst źródłowy z Live\nDla wszystkich wydarzeń", $sourceSection['lyrics'] ?? null, 'edycja Live aktualizuje część pieśni źródłowej');
$secondEventSong = $repo->eventSong($secondEventSongId);
expectSame('Refren zapisany w źródle', $secondEventSong['form'][0]['section_label'], 'zmiana źródła trafia do innego wydarzenia bez nadpisania');
expectSame(true, (int) $repo->liveSnapshot($secondEventId, 'pl')['revision'] > $secondRevisionBefore, 'zmiana źródła odświeża inne aktywne repertuary');

$repo->updateLiveSetting($eventId, 'song', $eventSongId, 'transpose_steps', 2);
$snapshot = $repo->liveSnapshot($eventId, 'intl');
expectSame($firstFormId, $snapshot['state']['current_form_id'], 'stan live pozostaje spójny po zmianie ustawień');
expectSame(2, $snapshot['songs'][0]['transpose_steps'], 'zmiana transpozycji zapisuje się w wydarzeniu');

echo "\nWszystkie testy zakończone powodzeniem.\n";
