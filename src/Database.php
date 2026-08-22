<?php

declare(strict_types=1);

namespace BandBook;

use PDO;
use RuntimeException;

final class Database
{
    public static function connect(array $config): PDO
    {
        $dsn = (string) $config['db_dsn'];

        if (str_starts_with($dsn, 'sqlite:')) {
            $file = substr($dsn, 7);
            $directory = dirname($file);
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException('Nie można utworzyć katalogu storage.');
            }
        }

        $pdo = new PDO(
            $dsn,
            $config['db_user'] ?? null,
            $config['db_password'] ?? null,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA busy_timeout = 5000');
        }

        self::migrate($pdo);
        return $pdo;
    }

    private static function migrate(PDO $pdo): void
    {
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $id = $mysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $integer = $mysql ? 'INT' : 'INTEGER';
        $text = $mysql ? 'LONGTEXT' : 'TEXT';

        $statements = [
            "CREATE TABLE IF NOT EXISTS users (
                id {$id},
                username VARCHAR(120) NOT NULL UNIQUE,
                display_name VARCHAR(160) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(32) NOT NULL DEFAULT 'admin',
                notation_profile VARCHAR(32) NOT NULL DEFAULT 'pl',
                created_at VARCHAR(32) NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS songs (
                id {$id},
                title VARCHAR(240) NOT NULL,
                alt_title VARCHAR(240) NULL,
                source_key VARCHAR(24) NULL,
                bpm {$integer} NULL,
                meter VARCHAR(24) NULL,
                comment {$text} NULL,
                notation_profile VARCHAR(32) NOT NULL DEFAULT 'pl',
                archived {$integer} NOT NULL DEFAULT 0,
                created_at VARCHAR(32) NOT NULL,
                updated_at VARCHAR(32) NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS song_sections (
                id {$id},
                song_id {$integer} NOT NULL,
                type VARCHAR(40) NOT NULL,
                label VARCHAR(160) NOT NULL,
                position {$integer} NOT NULL,
                lyrics {$text} NOT NULL,
                chords {$text} NOT NULL,
                comment {$text} NULL,
                archived {$integer} NOT NULL DEFAULT 0,
                FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS song_default_form_items (
                id {$id},
                song_id {$integer} NOT NULL,
                section_id {$integer} NOT NULL,
                position {$integer} NOT NULL,
                transpose_steps {$integer} NOT NULL DEFAULT 0,
                comment {$text} NULL,
                FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE,
                FOREIGN KEY (section_id) REFERENCES song_sections(id)
            )",
            "CREATE TABLE IF NOT EXISTS categories (
                id {$id},
                name VARCHAR(180) NOT NULL,
                group_name VARCHAR(40) NOT NULL DEFAULT 'section',
                sort_order {$integer} NOT NULL DEFAULT 0,
                color VARCHAR(24) NULL,
                created_at VARCHAR(32) NOT NULL,
                UNIQUE (name, group_name)
            )",
            "CREATE TABLE IF NOT EXISTS song_categories (
                song_id {$integer} NOT NULL,
                category_id {$integer} NOT NULL,
                position {$integer} NOT NULL DEFAULT 0,
                PRIMARY KEY (song_id, category_id),
                FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS events (
                id {$id},
                name VARCHAR(240) NOT NULL,
                planned_at VARCHAR(32) NULL,
                location VARCHAR(240) NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'draft',
                comment {$text} NULL,
                background_image VARCHAR(255) NULL,
                public_token VARCHAR(80) NOT NULL UNIQUE,
                live_revision {$integer} NOT NULL DEFAULT 1,
                created_at VARCHAR(32) NOT NULL,
                updated_at VARCHAR(32) NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS event_songs (
                id {$id},
                event_id {$integer} NOT NULL,
                song_id {$integer} NOT NULL,
                position {$integer} NOT NULL,
                transpose_steps {$integer} NOT NULL DEFAULT 0,
                bpm_override {$integer} NULL,
                comment {$text} NULL,
                FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
                FOREIGN KEY (song_id) REFERENCES songs(id)
            )",
            "CREATE TABLE IF NOT EXISTS event_song_form_items (
                id {$id},
                event_song_id {$integer} NOT NULL,
                section_id {$integer} NOT NULL,
                position {$integer} NOT NULL,
                transpose_steps {$integer} NOT NULL DEFAULT 0,
                comment {$text} NULL,
                label_override VARCHAR(160) NULL,
                lyrics_override {$text} NULL,
                chords_override {$text} NULL,
                FOREIGN KEY (event_song_id) REFERENCES event_songs(id) ON DELETE CASCADE,
                FOREIGN KEY (section_id) REFERENCES song_sections(id)
            )",
            "CREATE TABLE IF NOT EXISTS live_states (
                event_id {$integer} PRIMARY KEY,
                event_song_id {$integer} NULL,
                current_form_id {$integer} NULL,
                next_form_id {$integer} NULL,
                output_mode VARCHAR(20) NOT NULL DEFAULT 'text',
                revision {$integer} NOT NULL DEFAULT 1,
                updated_at VARCHAR(32) NOT NULL,
                updated_by {$integer} NULL,
                FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
                FOREIGN KEY (event_song_id) REFERENCES event_songs(id) ON DELETE SET NULL,
                FOREIGN KEY (current_form_id) REFERENCES event_song_form_items(id) ON DELETE SET NULL,
                FOREIGN KEY (next_form_id) REFERENCES event_song_form_items(id) ON DELETE SET NULL,
                FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
            )",
            "CREATE TABLE IF NOT EXISTS change_log (
                id {$id},
                user_id {$integer} NULL,
                entity_type VARCHAR(40) NOT NULL,
                entity_id {$integer} NOT NULL,
                action VARCHAR(80) NOT NULL,
                created_at VARCHAR(32) NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )",
        ];

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }

        self::ensureColumn($pdo, 'event_song_form_items', 'label_override', 'VARCHAR(160) NULL');
        self::ensureColumn($pdo, 'event_song_form_items', 'lyrics_override', $text . ' NULL');
        self::ensureColumn($pdo, 'event_song_form_items', 'chords_override', $text . ' NULL');
        self::ensureColumn($pdo, 'events', 'background_image', 'VARCHAR(255) NULL');
        self::ensureColumn($pdo, 'live_states', 'output_mode', "VARCHAR(20) NOT NULL DEFAULT 'text'");
        self::backfillCategories($pdo);
    }

    private static function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
    {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $columns = $pdo->query("PRAGMA table_info({$table})")->fetchAll();
            foreach ($columns as $existing) {
                if (($existing['name'] ?? null) === $column) {
                    return;
                }
            }
        } else {
            $statement = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE ?");
            $statement->execute([$column]);
            if ($statement->fetch() !== false) {
                return;
            }
        }

        $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    }

    private static function backfillCategories(PDO $pdo): void
    {
        if ((int) $pdo->query('SELECT COUNT(*) FROM songs')->fetchColumn() === 0) {
            return;
        }

        $assigned = (int) $pdo->query('SELECT COUNT(*) FROM song_categories')->fetchColumn();
        if ($assigned > 0) {
            return;
        }

        $sectionOrder = [
            'Módl się śpiewem',
            'Śpiewem radujmy innych',
            'Duchu napełnij życie me',
            'Ofiaruję Panie Ci cały mój świat',
            'Pasterzu czuwaj zawsze przy mnie',
            'Z Maryją przez świat',
            'Nie siedź tyle tylko rusz się',
            'Prawdziwe Dyskotekowe hity ciszy',
            'Tradycja zawsze na czasie',
            'Wielki Post',
            'Wielkanoc',
            'Inności piękności',
            'Części stałe',
        ];
        $sectionSort = array_flip($sectionOrder);
        $positions = [];

        $pdo->beginTransaction();
        try {
            $songs = $pdo->query('SELECT id, comment FROM songs WHERE archived = 0 ORDER BY id')->fetchAll();
            foreach ($songs as $song) {
                $comment = (string) ($song['comment'] ?? '');
                $assignments = [];

                if (str_contains($comment, 'Import: Śpiewnik guanelliański')) {
                    $assignments[] = ['Śpiewnik guanelliański', 'source', 0];
                    if (preg_match('/Kategoria:\s*([^\r\n]+)/u', $comment, $match)) {
                        $name = trim($match[1]);
                        $assignments[] = [$name, 'section', (int) ($sectionSort[$name] ?? 999)];
                    }
                }

                if (str_contains($comment, 'Import: OpenLP')) {
                    $assignments[] = ['OpenLP', 'source', 1];
                    if (preg_match('/^Śpiewnik:\s*([^\r\n]+)/mu', $comment, $match)) {
                        foreach (array_filter(array_map('trim', explode(',', $match[1]))) as $name) {
                            $assignments[] = [$name, 'songbook', 0];
                        }
                    }
                }

                foreach ($assignments as [$name, $group, $sortOrder]) {
                    $categoryId = self::ensureCategory($pdo, $name, $group, $sortOrder);
                    $positionKey = $group . '|' . $name;
                    $position = $positions[$positionKey] ?? 0;
                    self::assignCategory($pdo, (int) $song['id'], $categoryId, $position);
                    $positions[$positionKey] = $position + 1;
                }
            }
            $pdo->commit();
        } catch (\Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }

    private static function ensureCategory(PDO $pdo, string $name, string $group, int $sortOrder): int
    {
        $select = $pdo->prepare('SELECT id FROM categories WHERE name = ? AND group_name = ?');
        $select->execute([$name, $group]);
        $id = $select->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }

        $insert = $pdo->prepare(
            'INSERT INTO categories (name, group_name, sort_order, color, created_at) VALUES (?, ?, ?, NULL, ?)'
        );
        $insert->execute([$name, $group, $sortOrder, date(DATE_ATOM)]);
        return (int) $pdo->lastInsertId();
    }

    private static function assignCategory(PDO $pdo, int $songId, int $categoryId, int $position): void
    {
        $select = $pdo->prepare('SELECT 1 FROM song_categories WHERE song_id = ? AND category_id = ?');
        $select->execute([$songId, $categoryId]);
        if ($select->fetchColumn() !== false) {
            return;
        }
        $insert = $pdo->prepare('INSERT INTO song_categories (song_id, category_id, position) VALUES (?, ?, ?)');
        $insert->execute([$songId, $categoryId, $position]);
    }
}
