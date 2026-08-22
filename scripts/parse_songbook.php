<?php

declare(strict_types=1);

/**
 * Konwertuje eksport Markdown z Dokumentów Google do danych importowych BandBook.
 *
 * Użycie:
 * php scripts/parse_songbook.php źródło.md data/songbook.json [raport.json]
 */

if ($argc < 3) {
    fwrite(STDERR, "Użycie: php scripts/parse_songbook.php źródło.md wynik.json [raport.json]\n");
    exit(2);
}

$sourcePath = $argv[1];
$outputPath = $argv[2];
$reportPath = $argv[3] ?? null;
$source = file_get_contents($sourcePath);

if ($source === false) {
    fwrite(STDERR, "Nie można odczytać pliku: {$sourcePath}\n");
    exit(1);
}

$lines = preg_split('/\R/u', str_replace("\u{00A0}", ' ', $source)) ?: [];
$titleIndexes = [];
$titleNumbers = [];

foreach ($lines as $index => $line) {
    if (preg_match('/^\s*(\d+)\.\s+(.+?)\s*$/u', $line, $match)) {
        $titleIndexes[] = $index;
        $titleNumbers[$index] = (int) $match[1];
    }
}

$ignoredLines = [];
$categoryAtTitle = [];
$currentCategory = 'Pozostałe';

foreach ($titleIndexes as $titleIndex) {
    if (($titleNumbers[$titleIndex] ?? 0) === 1) {
        $cursor = $titleIndex - 1;
        while ($cursor >= 0 && trim($lines[$cursor]) === '') {
            $cursor--;
        }

        while ($cursor >= 0) {
            $candidate = cleanMarkdown($lines[$cursor]);
            if (mbSafeLength($candidate) <= 3 && !str_contains($lines[$cursor], "\t")) {
                $ignoredLines[$cursor] = true;
                $cursor--;
                while ($cursor >= 0 && trim($lines[$cursor]) === '') {
                    $cursor--;
                }
                continue;
            }

            if (
                $candidate !== ''
                && mbSafeLength($candidate) <= 90
                && !str_contains($lines[$cursor], "\t")
                && !preg_match('/^\s*\d+[.)]/u', $candidate)
            ) {
                $currentCategory = $candidate;
                $ignoredLines[$cursor] = true;
            }
            break;
        }
    }
    $categoryAtTitle[$titleIndex] = $currentCategory;
}

$fixedPartsIndex = null;
foreach ($lines as $index => $line) {
    if (preg_match('/^\s*Części stałe\s*:?\s*$/iu', cleanMarkdown($line))) {
        $fixedPartsIndex = $index;
        $ignoredLines[$index] = true;
        break;
    }
}

$songs = [];
$warnings = [];
$sourceTitleCounts = [];

foreach ($titleIndexes as $position => $titleIndex) {
    if ($fixedPartsIndex !== null && $titleIndex > $fixedPartsIndex) {
        break;
    }

    preg_match('/^\s*\d+\.\s+(.+?)\s*$/u', $lines[$titleIndex], $titleMatch);
    $title = cleanMarkdown($titleMatch[1] ?? '');
    if ($title === '') {
        continue;
    }

    $nextTitle = $titleIndexes[$position + 1] ?? count($lines);
    $end = $fixedPartsIndex !== null && $fixedPartsIndex < $nextTitle
        ? $fixedPartsIndex
        : $nextTitle;

    $bodyLines = [];
    for ($index = $titleIndex + 1; $index < $end; $index++) {
        if (!isset($ignoredLines[$index])) {
            $bodyLines[] = $lines[$index];
        }
    }

    [$sections, $songWarnings] = parseSections($bodyLines);
    if ($sections === []) {
        $sections[] = makeSection('custom', 'Całość', [['lyrics' => '', 'chords' => '']]);
        $songWarnings[] = 'Nie wykryto treści pieśni.';
    }

    $baseTitle = $title;
    $sourceTitleCounts[$baseTitle] = ($sourceTitleCounts[$baseTitle] ?? 0) + 1;
    if ($sourceTitleCounts[$baseTitle] > 1) {
        $title .= ' (wersja ' . $sourceTitleCounts[$baseTitle] . ')';
        $songWarnings[] = "Powtórzony tytuł źródłowy: {$baseTitle}.";
    }

    $category = $categoryAtTitle[$titleIndex] ?? 'Pozostałe';
    $comment = 'Import: Śpiewnik guanelliański · Kategoria: ' . $category;
    if ($songWarnings !== []) {
        $comment .= "\nDo sprawdzenia: " . implode(' ', array_unique($songWarnings));
    }

    $songs[] = [
        'title' => $title,
        'alt_title' => '',
        'source_key' => '',
        'bpm' => null,
        'meter' => '',
        'comment' => $comment,
        'notation_profile' => 'pl',
        'sections' => $sections,
        'form' => buildDefaultForm($sections),
        'import_category' => $category,
        'import_warnings' => array_values(array_unique($songWarnings)),
    ];

    if ($songWarnings !== []) {
        $warnings[$title] = array_values(array_unique($songWarnings));
    }
}

if ($fixedPartsIndex !== null) {
    $specialSongs = parseFixedParts(array_slice($lines, $fixedPartsIndex + 1));
    foreach ($specialSongs as $song) {
        $songs[] = $song;
    }
}

$outputDirectory = dirname($outputPath);
if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0775, true) && !is_dir($outputDirectory)) {
    fwrite(STDERR, "Nie można utworzyć katalogu: {$outputDirectory}\n");
    exit(1);
}

$json = json_encode($songs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false || file_put_contents($outputPath, $json . "\n") === false) {
    fwrite(STDERR, "Nie można zapisać pliku: {$outputPath}\n");
    exit(1);
}

$report = [
    'source' => basename($sourcePath),
    'generated_at' => date('c'),
    'songs' => count($songs),
    'sections' => array_sum(array_map(static fn (array $song): int => count($song['sections']), $songs)),
    'songs_with_warnings' => count($warnings),
    'warnings' => $warnings,
    'categories' => array_count_values(array_map(static fn (array $song): string => $song['import_category'], $songs)),
];

if ($reportPath !== null) {
    file_put_contents(
        $reportPath,
        json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
    );
}

echo "Pieśni: {$report['songs']}\n";
echo "Części: {$report['sections']}\n";
echo "Pieśni z ostrzeżeniami: {$report['songs_with_warnings']}\n";

function parseSections(array $lines): array
{
    $sections = [];
    $warnings = [];
    $currentRows = [];
    $currentType = null;
    $currentLabel = null;
    $multiColumnDetected = false;

    $flush = static function () use (&$sections, &$currentRows, &$currentType, &$currentLabel): void {
        if ($currentRows === []) {
            return;
        }
        $sections[] = makeSection($currentType ?? 'custom', $currentLabel ?? '', $currentRows);
        $currentRows = [];
        $currentType = null;
        $currentLabel = null;
    };

    foreach ($lines as $rawLine) {
        $rawLine = rtrim(str_replace("\u{00A0}", ' ', (string) $rawLine));
        if (trim($rawLine) === '') {
            $flush();
            continue;
        }

        $cells = preg_split('/\t+/u', $rawLine) ?: [$rawLine];
        $lyrics = cleanMarkdown(array_shift($cells) ?? '');
        $remainder = cleanMarkdown(implode(' ', $cells));
        $chords = '';

        if ($remainder !== '') {
            if (looksLikeChords($remainder)) {
                $chords = $remainder;
            } else {
                $lyrics = trim($lyrics . '  |  ' . $remainder);
                $multiColumnDetected = true;
            }
        }

        if ($lyrics === '' && $chords !== '') {
            if ($currentRows !== []) {
                $lastIndex = count($currentRows) - 1;
                $currentRows[$lastIndex]['chords'] = trim($currentRows[$lastIndex]['chords'] . ' ' . $chords);
            } else {
                $currentRows[] = ['lyrics' => '', 'chords' => $chords];
            }
            continue;
        }

        $marker = detectSectionMarker($lyrics);
        if ($marker !== null) {
            if ($currentRows !== []) {
                $flush();
            }
            $currentType = $marker['type'];
            $currentLabel = $marker['label'];
            $lyrics = $marker['lyrics'];
        }

        if ($lyrics !== '' || $chords !== '') {
            $currentRows[] = ['lyrics' => $lyrics, 'chords' => $chords];
        }
    }
    $flush();

    if ($multiColumnDetected) {
        $warnings[] = 'Wykryto układ wielokolumnowy zapisany znakiem „|”.';
    }

    if (count($sections) === 1 && $sections[0]['label'] === '') {
        $sections[0]['label'] = 'Całość';
    } else {
        $customNumber = 1;
        foreach ($sections as &$section) {
            if ($section['label'] === '') {
                $section['label'] = 'Część ' . $customNumber++;
            }
        }
        unset($section);
    }

    $labels = [];
    foreach ($sections as &$section) {
        $base = $section['label'];
        $labels[$base] = ($labels[$base] ?? 0) + 1;
        if ($labels[$base] > 1) {
            $section['label'] = $base . ' ' . $labels[$base];
        }
    }
    unset($section);

    return [$sections, $warnings];
}

function detectSectionMarker(string $lyrics): ?array
{
    if (preg_match('/^\s*(?:ref(?:ren)?\.?|r)\s*[:.]?\s*(.*)$/iu', $lyrics, $match)) {
        return [
            'type' => 'chorus',
            'label' => 'Refren',
            'lyrics' => preg_replace('/^\s*\/\s*/u', '', $match[1]) ?? $match[1],
        ];
    }

    if (preg_match('/^\s*\[?bridge\]?\s*[:.]?\s*(.*)$/iu', $lyrics, $match)) {
        return ['type' => 'bridge', 'label' => 'Bridge', 'lyrics' => $match[1]];
    }

    if (preg_match('/^\s*(\d+)[.)]\s*(.*)$/u', $lyrics, $match)) {
        return ['type' => 'verse', 'label' => 'Zwrotka ' . $match[1], 'lyrics' => $match[2]];
    }

    return null;
}

function makeSection(string $type, string $label, array $rows): array
{
    return [
        'type' => $type,
        'label' => $label,
        'lyrics' => implode("\n", array_map(static fn (array $row): string => $row['lyrics'], $rows)),
        'chords' => implode("\n", array_map(static fn (array $row): string => $row['chords'], $rows)),
        'comment' => '',
    ];
}

function buildDefaultForm(array $sections): array
{
    $chorusIndexes = [];
    $verseIndexes = [];
    foreach ($sections as $index => $section) {
        if ($section['type'] === 'chorus') {
            $chorusIndexes[] = $index;
        } elseif ($section['type'] === 'verse') {
            $verseIndexes[] = $index;
        }
    }

    $onlyVersesAndChorus = count($chorusIndexes) + count($verseIndexes) === count($sections);
    if ($onlyVersesAndChorus && count($chorusIndexes) === 1 && count($verseIndexes) > 1) {
        $chorus = $chorusIndexes[0];
        $form = [];
        if ($chorus === 0) {
            foreach ($verseIndexes as $verse) {
                $form[] = $chorus;
                $form[] = $verse;
            }
            $form[] = $chorus;
            return $form;
        }

        foreach ($verseIndexes as $verse) {
            $form[] = $verse;
            $form[] = $chorus;
        }
        return $form;
    }

    return array_keys($sections);
}

function parseFixedParts(array $lines): array
{
    $items = [];
    $current = null;

    $flush = static function () use (&$items, &$current): void {
        if ($current === null) {
            return;
        }
        [$sections, $warnings] = parseSections($current['lines']);
        if ($sections === []) {
            $sections = [makeSection('custom', 'Całość', [['lyrics' => $current['title'], 'chords' => '']])];
        }
        $hasLyrics = false;
        foreach ($sections as $section) {
            if (trim($section['lyrics']) !== '') {
                $hasLyrics = true;
                break;
            }
        }
        if (!$hasLyrics) {
            $sections[0]['lyrics'] = $current['title'];
        }
        $comment = 'Import: Śpiewnik guanelliański · Kategoria: Części stałe';
        if ($warnings !== []) {
            $comment .= "\nDo sprawdzenia: " . implode(' ', $warnings);
        }
        $items[] = [
            'title' => $current['title'],
            'alt_title' => '',
            'source_key' => '',
            'bpm' => null,
            'meter' => '',
            'comment' => $comment,
            'notation_profile' => 'pl',
            'sections' => $sections,
            'form' => array_keys($sections),
            'import_category' => 'Części stałe',
            'import_warnings' => $warnings,
        ];
        $current = null;
    };

    foreach ($lines as $line) {
        $clean = cleanMarkdown((string) $line);
        $isVersionedPart = preg_match('/^(Panie|Baranku)\s+v(\d+)\s*:?\s*(.*)$/iu', $clean, $match) === 1;
        $isSanctus = preg_match('/^(Święty)\s*:\s*(.*)$/iu', $clean, $sanctusMatch) === 1;
        if ($isVersionedPart || $isSanctus) {
            $flush();
            if ($isSanctus) {
                $match = [$sanctusMatch[0], $sanctusMatch[1], '', $sanctusMatch[2]];
            }
            $name = $match[1];
            $version = $match[2] ?? '';
            $title = $name . ($version !== '' ? ' — wersja ' . $version : ' — część stała');

            $rawCells = preg_split('/\t+/u', (string) $line) ?: [];
            array_shift($rawCells);
            $inlineChords = cleanMarkdown(implode(' ', $rawCells));
            $current = ['title' => $title, 'lines' => []];
            if ($inlineChords !== '') {
                $current['lines'][] = "\t" . $inlineChords;
            }
            continue;
        }

        if ($current !== null) {
            $current['lines'][] = $line;
        }
    }
    $flush();
    return $items;
}

function looksLikeChords(string $text): bool
{
    $text = trim($text);
    if ($text === '') {
        return false;
    }

    $tokens = preg_split('/\s+/u', $text) ?: [];
    $recognized = 0;
    $musical = 0;

    foreach ($tokens as $token) {
        $token = trim($token, "|()[]{}.,;: ");
        if ($token === '') {
            continue;
        }
        if (preg_match('/^(?:x?\d+|\/x?\d+|\/\d+\/|\-|\/)+$/iu', $token)) {
            $recognized++;
            continue;
        }
        if (preg_match('/^[A-Ha-h](?:is|es|#|b)?(?:m(?!aj))?(?:maj|min|sus|add|dim|aug)?[0-9+\-]*(?:\/[A-Ha-h](?:is|es|#|b)?)?(?:x\d+)?$/u', $token)) {
            $recognized++;
            $musical++;
        }
    }

    return $musical > 0 && $recognized / max(1, count($tokens)) >= 0.6;
}

function cleanMarkdown(string $text): string
{
    $text = preg_replace('/!\[[^\]]*\]\[[^\]]+\]/u', '', $text) ?? $text;
    $text = str_replace(['**', '__'], '', $text);
    $text = preg_replace('/\\\\([!\[\]{}().-])/u', '$1', $text) ?? $text;
    $text = preg_replace('/[ \t]+$/u', '', $text) ?? $text;
    return trim($text);
}

function mbSafeLength(string $text): int
{
    return preg_match_all('/./us', $text, $matches) ?: 0;
}
