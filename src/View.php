<?php

declare(strict_types=1);

namespace BandBook;

final class View
{
    public static function render(string $template, array $data = [], string $layout = 'layout'): void
    {
        $views = dirname(__DIR__) . '/views';
        extract($data, EXTR_SKIP);
        ob_start();
        require $views . '/' . $template . '.php';
        $content = (string) ob_get_clean();
        require $views . '/' . $layout . '.php';
    }

    public static function statusLabel(string $status): string
    {
        return [
            'draft' => 'Szkic',
            'ready' => 'Gotowe',
            'live' => 'W trakcie',
            'finished' => 'Zakończone',
            'archived' => 'Archiwalne',
        ][$status] ?? $status;
    }

    public static function eventDate(?string $date): string
    {
        if (!$date) {
            return 'Termin nieustalony';
        }
        $timestamp = strtotime($date);
        return $timestamp ? date('d.m.Y, H:i', $timestamp) : $date;
    }
}
