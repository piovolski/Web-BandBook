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
            "CREATE TABLE IF NOT EXISTS events (
                id {$id},
                name VARCHAR(240) NOT NULL,
                planned_at VARCHAR(32) NULL,
                location VARCHAR(240) NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'draft',
                comment {$text} NULL,
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
                FOREIGN KEY (event_song_id) REFERENCES event_songs(id) ON DELETE CASCADE,
                FOREIGN KEY (section_id) REFERENCES song_sections(id)
            )",
            "CREATE TABLE IF NOT EXISTS live_states (
                event_id {$integer} PRIMARY KEY,
                event_song_id {$integer} NULL,
                current_form_id {$integer} NULL,
                next_form_id {$integer} NULL,
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
    }
}
