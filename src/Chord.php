<?php

declare(strict_types=1);

namespace BandBook;

final class Chord
{
    private const PL_ROOTS = [
        'ces' => 11, 'cis' => 1, 'des' => 1, 'dis' => 3, 'es' => 3,
        'eis' => 5, 'fes' => 4, 'fis' => 6, 'ges' => 6, 'gis' => 8,
        'as' => 8, 'ais' => 10, 'his' => 0,
        'c' => 0, 'd' => 2, 'e' => 4, 'f' => 5, 'g' => 7,
        'a' => 9, 'b' => 10, 'h' => 11,
    ];

    private const INTL_ROOTS = [
        'cb' => 11, 'c#' => 1, 'db' => 1, 'd#' => 3, 'eb' => 3,
        'e#' => 5, 'fb' => 4, 'f#' => 6, 'gb' => 6, 'g#' => 8,
        'ab' => 8, 'a#' => 10, 'bb' => 10,
        'c' => 0, 'd' => 2, 'e' => 4, 'f' => 5, 'g' => 7,
        'a' => 9, 'b' => 11,
    ];

    private const PL_SHARP_MAJOR = ['C', 'Cis', 'D', 'Dis', 'E', 'F', 'Fis', 'G', 'Gis', 'A', 'B', 'H'];
    private const PL_SHARP_MINOR = ['c', 'cis', 'd', 'dis', 'e', 'f', 'fis', 'g', 'gis', 'a', 'b', 'h'];
    private const PL_FLAT_MAJOR = ['C', 'Des', 'D', 'Es', 'E', 'F', 'Ges', 'G', 'As', 'A', 'B', 'H'];
    private const PL_FLAT_MINOR = ['c', 'des', 'd', 'es', 'e', 'f', 'ges', 'g', 'as', 'a', 'b', 'h'];
    private const INTL_SHARP = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B'];
    private const INTL_FLAT = ['C', 'Db', 'D', 'Eb', 'E', 'F', 'Gb', 'G', 'Ab', 'A', 'Bb', 'B'];

    public static function transposeLines(string $input, int $steps, string $inputProfile, string $outputProfile): string
    {
        $lines = preg_split('/\R/u', $input) ?: [];
        return implode("\n", array_map(
            fn (string $line): string => self::transposeLine($line, $steps, $inputProfile, $outputProfile),
            $lines
        ));
    }

    public static function transposeLine(string $line, int $steps, string $inputProfile, string $outputProfile): string
    {
        return (string) preg_replace_callback(
            '/(?<![\p{L}\p{N}#])([\(\[]?)([A-Ha-h](?:is|es|#|b)?)([^\s\)\]\/,]*)(?:\/([A-Ha-h](?:is|es|#|b)?))?([\)\]]?)(?![\p{L}\p{N}#])/u',
            function (array $match) use ($steps, $inputProfile, $outputProfile): string {
                $rootToken = $match[2];
                $suffix = $match[3] ?? '';
                $bassToken = $match[4] ?? '';
                $suffixMarksMinor = preg_match('/^m(?!aj)/i', $suffix) === 1;
                $minor = ctype_lower(substr($rootToken, 0, 1)) || $suffixMarksMinor;

                $root = self::pitch($rootToken, $inputProfile);
                if ($root === null) {
                    return $match[0];
                }

                if ($suffixMarksMinor) {
                    $suffix = substr($suffix, 1);
                }

                $preferFlats = self::prefersFlats($rootToken, $inputProfile);
                $result = ($match[1] ?? '') . self::formatPitch(self::wrap($root + $steps), $minor, $outputProfile, $preferFlats);
                $result .= $outputProfile === 'intl' && $minor ? 'm' . $suffix : $suffix;

                if ($bassToken !== '') {
                    $bass = self::pitch($bassToken, $inputProfile);
                    if ($bass !== null) {
                        $result .= '/' . self::formatPitch(self::wrap($bass + $steps), false, $outputProfile, $preferFlats);
                    }
                }

                return $result . ($match[5] ?? '');
            },
            $line
        );
    }

    public static function label(string $profile): string
    {
        return $profile === 'intl' ? 'B/Bb + końcówka m' : 'H/B + małe molowe';
    }

    private static function pitch(string $token, string $profile): ?int
    {
        $key = strtolower($token);
        $map = $profile === 'intl' ? self::INTL_ROOTS : self::PL_ROOTS;

        if (array_key_exists($key, $map)) {
            return $map[$key];
        }

        // Oba popularne zapisy krzyżyków są przyjmowane niezależnie od profilu.
        $fallback = $profile === 'intl' ? self::PL_ROOTS : self::INTL_ROOTS;
        return $fallback[$key] ?? null;
    }

    private static function formatPitch(int $pitch, bool $minor, string $profile, bool $flats): string
    {
        if ($profile === 'intl') {
            return ($flats ? self::INTL_FLAT : self::INTL_SHARP)[$pitch];
        }

        if ($minor) {
            return ($flats ? self::PL_FLAT_MINOR : self::PL_SHARP_MINOR)[$pitch];
        }

        return ($flats ? self::PL_FLAT_MAJOR : self::PL_SHARP_MAJOR)[$pitch];
    }

    private static function prefersFlats(string $token, string $profile): bool
    {
        if ($profile === 'intl') {
            return str_contains($token, 'b');
        }

        $root = strtolower($token);
        return in_array($root, ['b', 'des', 'es', 'ges', 'as', 'ces', 'fes'], true);
    }

    private static function wrap(int $pitch): int
    {
        return (($pitch % 12) + 12) % 12;
    }
}
