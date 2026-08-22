<?php

declare(strict_types=1);

/**
 * Konwertuje katalog eksportu OpenLP/OpenLyrics do danych BandBook.
 *
 * Użycie:
 * php scripts/parse_openlp.php katalog_xml wynik.json raport.json [istniejące_pieśni.json]
 */

if ($argc < 4) {
    fwrite(STDERR, "Użycie: php scripts/parse_openlp.php katalog_xml wynik.json raport.json [istniejące_pieśni.json]\n");
    exit(2);
}

[$script, $sourceDirectory, $outputPath, $reportPath] = $argv;
$existingPath = $argv[4] ?? null;

if (!is_dir($sourceDirectory)) {
    fwrite(STDERR, "Nie znaleziono katalogu: {$sourceDirectory}\n");
    exit(1);
}

$existingTitles = [];
if ($existingPath !== null) {
    $existingJson = file_get_contents($existingPath);
    $existingSongs = $existingJson === false ? null : json_decode($existingJson, true);
    if (!is_array($existingSongs)) {
        fwrite(STDERR, "Nie można odczytać istniejącego zestawu: {$existingPath}\n");
        exit(1);
    }
    foreach ($existingSongs as $existingSong) {
        if (is_array($existingSong) && isset($existingSong['title'])) {
            $existingTitles[normalizeTitle((string) $existingSong['title'])] = true;
        }
    }
}

$paths = glob(rtrim($sourceDirectory, '/\\') . DIRECTORY_SEPARATOR . '*.xml') ?: [];
natcasesort($paths);
$paths = array_values($paths);

$songs = [];
$usedTitles = $existingTitles;
$fingerprints = [];
$invalidFiles = [];
$duplicateFiles = [];
$renamedTitles = [];
$sourceTitles = [];
$sectionCount = 0;
$songsWithOrder = 0;
$songsWithoutOrder = 0;
$songsWithSongbook = 0;

libxml_use_internal_errors(true);

foreach ($paths as $path) {
    libxml_clear_errors();
    $document = new DOMDocument();
    $document->preserveWhiteSpace = true;
    if (!$document->load($path, LIBXML_NONET)) {
        $errors = array_map(
            static fn (LibXMLError $error): string => trim($error->message),
            libxml_get_errors()
        );
        $invalidFiles[basename($path)] = array_values(array_unique($errors));
        continue;
    }

    $xpath = new DOMXPath($document);
    $titleNodes = $xpath->query('/*[local-name()="song"]/*[local-name()="properties"]/*[local-name()="titles"]/*[local-name()="title"]');
    $titles = nodeTexts($titleNodes);
    $baseTitle = trim($titles[0] ?? '');
    if ($baseTitle === '') {
        $invalidFiles[basename($path)] = ['Brak tytułu.'];
        continue;
    }

    $authors = nodeTexts($xpath->query('/*[local-name()="song"]/*[local-name()="properties"]/*[local-name()="authors"]/*[local-name()="author"]'));
    $songbooks = [];
    $songbookNodes = $xpath->query('/*[local-name()="song"]/*[local-name()="properties"]/*[local-name()="songbooks"]/*[local-name()="songbook"]');
    if ($songbookNodes !== false) {
        foreach ($songbookNodes as $songbookNode) {
            if ($songbookNode instanceof DOMElement) {
                $name = trim($songbookNode->getAttribute('name'));
                if ($name !== '') {
                    $songbooks[] = $name;
                }
            }
        }
    }
    $songbooks = array_values(array_unique($songbooks));
    if ($songbooks !== []) {
        $songsWithSongbook++;
    }

    $sections = [];
    $sectionKeys = [];
    $verseNodes = $xpath->query('/*[local-name()="song"]/*[local-name()="lyrics"]/*[local-name()="verse"]');
    if ($verseNodes !== false) {
        foreach ($verseNodes as $versePosition => $verseNode) {
            if (!$verseNode instanceof DOMElement) {
                continue;
            }
            $openLyricsName = trim($verseNode->getAttribute('name')) ?: 'x' . ($versePosition + 1);
            $lineNodes = $xpath->query('./*[local-name()="lines"]', $verseNode);
            $blocks = [];
            if ($lineNodes !== false) {
                foreach ($lineNodes as $lineNode) {
                    $text = normalizeLyrics(nodeTextWithBreaks($lineNode));
                    if ($text !== '') {
                        $blocks[] = $text;
                    }
                }
            }
            $lyrics = implode("\n\n", $blocks);
            if ($lyrics === '') {
                continue;
            }

            [$type, $label] = sectionIdentity($openLyricsName);
            $key = 'openlp-' . count($sections);
            $sections[] = [
                'type' => $type,
                'label' => $label,
                'lyrics' => $lyrics,
                'chords' => '',
                'comment' => '',
            ];
            $sectionKeys[normalizeOrderToken($openLyricsName)] = count($sections) - 1;
        }
    }

    if ($sections === []) {
        $invalidFiles[basename($path)] = ['Brak niepustych części pieśni.'];
        continue;
    }

    $orderNode = $xpath->query('/*[local-name()="song"]/*[local-name()="properties"]/*[local-name()="verseOrder"]')?->item(0);
    $order = trim($orderNode?->textContent ?? '');
    $form = [];
    if ($order !== '') {
        $songsWithOrder++;
        foreach (preg_split('/\s+/u', $order) ?: [] as $orderToken) {
            $sectionIndex = $sectionKeys[normalizeOrderToken($orderToken)] ?? null;
            if ($sectionIndex !== null) {
                $form[] = $sectionIndex;
            }
        }
    } else {
        $songsWithoutOrder++;
    }
    if ($form === []) {
        $form = array_keys($sections);
    }

    $fingerprintPayload = [
        'title' => normalizeTitle($baseTitle),
        'sections' => array_map(
            static fn (array $section): array => [$section['type'], normalizeText($section['lyrics'])],
            $sections
        ),
        'form' => $form,
    ];
    $fingerprint = hash('sha256', json_encode($fingerprintPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    if (isset($fingerprints[$fingerprint])) {
        $duplicateFiles[basename($path)] = $fingerprints[$fingerprint];
        continue;
    }
    $fingerprints[$fingerprint] = basename($path);

    $title = uniqueTitle($baseTitle, $usedTitles, isset($existingTitles[normalizeTitle($baseTitle)]));
    if ($title !== $baseTitle) {
        $renamedTitles[basename($path)] = ['source' => $baseTitle, 'imported' => $title];
    }
    $usedTitles[normalizeTitle($title)] = true;

    $alternateTitles = array_values(array_unique(array_filter(array_map('trim', array_slice($titles, 1)))));
    $commentParts = ['Import: OpenLP', 'Plik: ' . basename($path)];
    if ($authors !== []) {
        $commentParts[] = 'Autorzy: ' . implode(', ', $authors);
    }
    if ($songbooks !== []) {
        $commentParts[] = 'Śpiewnik: ' . implode(', ', $songbooks);
    }
    $commentParts[] = 'Eksport nie zawierał chwytów.';

    $songs[] = [
        'title' => $title,
        'alt_title' => implode(' / ', $alternateTitles),
        'source_key' => '',
        'bpm' => null,
        'meter' => '',
        'comment' => implode("\n", $commentParts),
        'notation_profile' => 'pl',
        'sections' => $sections,
        'form' => $form,
        'import_source' => 'OpenLP',
        'import_file' => basename($path),
        'import_original_title' => $baseTitle,
        'import_authors' => $authors,
        'import_songbooks' => $songbooks,
    ];
    $sourceTitles[normalizeTitle($baseTitle)] = ($sourceTitles[normalizeTitle($baseTitle)] ?? 0) + 1;
    $sectionCount += count($sections);
}

$repeatedTitles = array_filter($sourceTitles, static fn (int $count): bool => $count > 1);
arsort($repeatedTitles);

$report = [
    'source_directory' => $sourceDirectory,
    'generated_at' => date(DATE_ATOM),
    'xml_files' => count($paths),
    'songs' => count($songs),
    'sections' => $sectionCount,
    'songs_with_explicit_order' => $songsWithOrder,
    'songs_without_explicit_order' => $songsWithoutOrder,
    'songs_with_songbook' => $songsWithSongbook,
    'identical_files_skipped' => count($duplicateFiles),
    'invalid_files' => $invalidFiles,
    'duplicates' => $duplicateFiles,
    'renamed_titles' => $renamedTitles,
    'repeated_source_titles' => $repeatedTitles,
];

writeJson($outputPath, $songs);
writeJson($reportPath, $report);

fwrite(STDOUT, "Pliki XML: " . count($paths) . "\n");
fwrite(STDOUT, "Pieśni: " . count($songs) . "\n");
fwrite(STDOUT, "Części: {$sectionCount}\n");
fwrite(STDOUT, "Identyczne kopie pominięte: " . count($duplicateFiles) . "\n");
fwrite(STDOUT, "Nieprawidłowe pliki: " . count($invalidFiles) . "\n");

function nodeTexts(DOMNodeList|false $nodes): array
{
    if ($nodes === false) {
        return [];
    }
    $values = [];
    foreach ($nodes as $node) {
        $value = trim($node->textContent);
        if ($value !== '') {
            $values[] = $value;
        }
    }
    return array_values(array_unique($values));
}

function nodeTextWithBreaks(DOMNode $node): string
{
    $text = '';
    foreach ($node->childNodes as $child) {
        if ($child instanceof DOMText || $child instanceof DOMCdataSection) {
            $text .= $child->nodeValue;
        } elseif ($child instanceof DOMElement && strtolower($child->localName) === 'br') {
            $text .= "\n";
        } else {
            $text .= nodeTextWithBreaks($child);
        }
    }
    return $text;
}

function normalizeLyrics(string $lyrics): string
{
    $lyrics = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $lyrics);
    $lines = array_map(static fn (string $line): string => rtrim($line), explode("\n", $lyrics));
    $lyrics = trim(implode("\n", $lines));
    return preg_replace('/\n{3,}/u', "\n\n", $lyrics) ?? $lyrics;
}

function sectionIdentity(string $name): array
{
    $name = trim($name);
    preg_match('/^([a-z]+)(.*)$/iu', $name, $match);
    $prefix = strtolower($match[1] ?? 'x');
    $suffix = trim($match[2] ?? '');
    $suffix = $suffix === '' ? '' : ' ' . $suffix;

    return match ($prefix[0] ?? 'x') {
        'v' => ['verse', 'Zwrotka' . $suffix],
        'c', 'r' => ['chorus', 'Refren' . $suffix],
        'b' => ['bridge', 'Bridge' . $suffix],
        'p' => ['prechorus', 'Pre-chorus' . $suffix],
        'i' => ['intro', 'Intro' . $suffix],
        'o' => ['outro', 'Outro' . $suffix],
        'e' => ['ending', 'Zakończenie' . $suffix],
        't' => ['tag', 'Tag' . $suffix],
        default => ['custom', 'Część ' . $name],
    };
}

function normalizeOrderToken(string $token): string
{
    return strtolower(trim($token));
}

function normalizeTitle(string $title): string
{
    $title = strtr($title, [
        'Ą' => 'ą', 'Ć' => 'ć', 'Ę' => 'ę', 'Ł' => 'ł', 'Ń' => 'ń',
        'Ó' => 'ó', 'Ś' => 'ś', 'Ź' => 'ź', 'Ż' => 'ż',
    ]);
    $title = strtolower(trim($title));
    return preg_replace('/\s+/u', ' ', $title) ?? $title;
}

function normalizeText(string $text): string
{
    return normalizeTitle(str_replace([',', '.', ';', ':', '!', '?'], '', $text));
}

function uniqueTitle(string $baseTitle, array $usedTitles, bool $collidesWithExisting): string
{
    if (!isset($usedTitles[normalizeTitle($baseTitle)])) {
        return $baseTitle;
    }

    if ($collidesWithExisting) {
        $candidate = $baseTitle . ' — OpenLP';
        $version = 2;
        while (isset($usedTitles[normalizeTitle($candidate)])) {
            $candidate = $baseTitle . ' — OpenLP ' . $version;
            $version++;
        }
        return $candidate;
    }

    $version = 2;
    $candidate = $baseTitle . ' — wersja ' . $version;
    while (isset($usedTitles[normalizeTitle($candidate)])) {
        $version++;
        $candidate = $baseTitle . ' — wersja ' . $version;
    }
    return $candidate;
}

function writeJson(string $path, array $data): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException("Nie można utworzyć katalogu: {$directory}");
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (file_put_contents($path, $json . "\n") === false) {
        throw new RuntimeException("Nie można zapisać pliku: {$path}");
    }
}
