<?php

declare(strict_types=1);

namespace BandBook;

use PDO;
use RuntimeException;
use Throwable;

final class Repository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function hasUsers(): bool
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
    }

    public function createAdmin(string $username, string $displayName, string $password, string $profile): int
    {
        $statement = $this->db->prepare(
            'INSERT INTO users (username, display_name, password_hash, role, notation_profile, created_at)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            trim($username),
            trim($displayName),
            password_hash($password, PASSWORD_DEFAULT),
            'admin',
            $profile === 'intl' ? 'intl' : 'pl',
            now(),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function authenticate(string $username, string $password): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $statement->execute([trim($username)]);
        $user = $statement->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        return $this->publicUser($user);
    }

    public function user(int $id): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM users WHERE id = ?');
        $statement->execute([$id]);
        $user = $statement->fetch();
        return $user ? $this->publicUser($user) : null;
    }

    public function updateNotation(int $userId, string $profile): void
    {
        $statement = $this->db->prepare('UPDATE users SET notation_profile = ? WHERE id = ?');
        $statement->execute([$profile === 'intl' ? 'intl' : 'pl', $userId]);
    }

    public function songs(bool $includeArchived = false): array
    {
        $where = $includeArchived ? '' : 'WHERE s.archived = 0';
        $titleOrder = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 's.title COLLATE NOCASE'
            : 's.title';
        return $this->db->query(
            "SELECT s.*, COUNT(ss.id) AS section_count
             FROM songs s
             LEFT JOIN song_sections ss ON ss.song_id = s.id AND ss.archived = 0
             {$where}
             GROUP BY s.id
             ORDER BY {$titleOrder}"
        )->fetchAll();
    }

    public function song(int $id): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM songs WHERE id = ?');
        $statement->execute([$id]);
        $song = $statement->fetch();
        if (!$song) {
            return null;
        }

        $sections = $this->db->prepare(
            'SELECT * FROM song_sections WHERE song_id = ? AND archived = 0 ORDER BY position, id'
        );
        $sections->execute([$id]);
        $song['sections'] = $sections->fetchAll();

        $form = $this->db->prepare(
            'SELECT f.*, s.label AS section_label, s.type AS section_type
             FROM song_default_form_items f
             JOIN song_sections s ON s.id = f.section_id
             WHERE f.song_id = ? ORDER BY f.position, f.id'
        );
        $form->execute([$id]);
        $song['form'] = $form->fetchAll();
        return $song;
    }

    public function saveSong(?int $id, array $data): int
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new RuntimeException('Tytuł pieśni jest wymagany.');
        }

        $sections = json_decode((string) ($data['sections_json'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR);
        $form = json_decode((string) ($data['form_json'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($sections) || count($sections) === 0) {
            throw new RuntimeException('Pieśń musi mieć co najmniej jedną część.');
        }

        $timestamp = now();
        $this->db->beginTransaction();
        try {
            if ($id === null) {
                $statement = $this->db->prepare(
                    'INSERT INTO songs (title, alt_title, source_key, bpm, meter, comment, notation_profile, archived, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?)'
                );
                $statement->execute([
                    $title,
                    $this->nullable($data['alt_title'] ?? null),
                    $this->nullable($data['source_key'] ?? null),
                    $this->nullableInt($data['bpm'] ?? null),
                    $this->nullable($data['meter'] ?? null),
                    $this->nullable($data['comment'] ?? null),
                    ($data['notation_profile'] ?? 'pl') === 'intl' ? 'intl' : 'pl',
                    $timestamp,
                    $timestamp,
                ]);
                $id = (int) $this->db->lastInsertId();
            } else {
                $statement = $this->db->prepare(
                    'UPDATE songs SET title = ?, alt_title = ?, source_key = ?, bpm = ?, meter = ?, comment = ?, notation_profile = ?, updated_at = ? WHERE id = ?'
                );
                $statement->execute([
                    $title,
                    $this->nullable($data['alt_title'] ?? null),
                    $this->nullable($data['source_key'] ?? null),
                    $this->nullableInt($data['bpm'] ?? null),
                    $this->nullable($data['meter'] ?? null),
                    $this->nullable($data['comment'] ?? null),
                    ($data['notation_profile'] ?? 'pl') === 'intl' ? 'intl' : 'pl',
                    $timestamp,
                    $id,
                ]);
            }

            $existing = $this->db->prepare('SELECT id FROM song_sections WHERE song_id = ?');
            $existing->execute([$id]);
            $existingIds = array_map('intval', array_column($existing->fetchAll(), 'id'));
            $keptIds = [];
            $keyMap = [];

            foreach (array_values($sections) as $position => $section) {
                if (!is_array($section)) {
                    continue;
                }
                $label = trim((string) ($section['label'] ?? ''));
                if ($label === '') {
                    throw new RuntimeException('Każda część musi mieć nazwę.');
                }
                $sectionId = isset($section['id']) && (int) $section['id'] > 0 ? (int) $section['id'] : null;
                $values = [
                    trim((string) ($section['type'] ?? 'verse')),
                    $label,
                    $position,
                    (string) ($section['lyrics'] ?? ''),
                    (string) ($section['chords'] ?? ''),
                    $this->nullable($section['comment'] ?? null),
                ];

                if ($sectionId !== null && in_array($sectionId, $existingIds, true)) {
                    $update = $this->db->prepare(
                        'UPDATE song_sections SET type = ?, label = ?, position = ?, lyrics = ?, chords = ?, comment = ?, archived = 0 WHERE id = ? AND song_id = ?'
                    );
                    $update->execute([...$values, $sectionId, $id]);
                } else {
                    $insert = $this->db->prepare(
                        'INSERT INTO song_sections (song_id, type, label, position, lyrics, chords, comment, archived)
                         VALUES (?, ?, ?, ?, ?, ?, ?, 0)'
                    );
                    $insert->execute([$id, ...$values]);
                    $sectionId = (int) $this->db->lastInsertId();
                }

                $keptIds[] = $sectionId;
                $clientKey = (string) ($section['key'] ?? ('id-' . $sectionId));
                $keyMap[$clientKey] = $sectionId;
                $keyMap['id-' . $sectionId] = $sectionId;
            }

            foreach (array_diff($existingIds, $keptIds) as $removedId) {
                $archive = $this->db->prepare('UPDATE song_sections SET archived = 1 WHERE id = ? AND song_id = ?');
                $archive->execute([$removedId, $id]);
            }

            $deleteForm = $this->db->prepare('DELETE FROM song_default_form_items WHERE song_id = ?');
            $deleteForm->execute([$id]);
            $insertForm = $this->db->prepare(
                'INSERT INTO song_default_form_items (song_id, section_id, position, transpose_steps, comment) VALUES (?, ?, ?, ?, ?)'
            );

            foreach (array_values($form) as $position => $item) {
                if (!is_array($item)) {
                    continue;
                }
                $sectionId = $keyMap[(string) ($item['sectionKey'] ?? '')] ?? null;
                if ($sectionId === null) {
                    continue;
                }
                $insertForm->execute([
                    $id,
                    $sectionId,
                    $position,
                    (int) ($item['transpose'] ?? 0),
                    $this->nullable($item['comment'] ?? null),
                ]);
            }

            if (count($form) === 0) {
                foreach ($keptIds as $position => $sectionId) {
                    $insertForm->execute([$id, $sectionId, $position, 0, null]);
                }
            }

            $this->log('song', $id, $data['id'] ?? null ? 'updated' : 'created');
            $this->db->commit();
            return $id;
        } catch (Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }

    public function archiveSong(int $id): void
    {
        $statement = $this->db->prepare('UPDATE songs SET archived = 1, updated_at = ? WHERE id = ?');
        $statement->execute([now(), $id]);
        $this->log('song', $id, 'archived');
    }

    public function events(): array
    {
        return $this->db->query(
            'SELECT e.*, COUNT(es.id) AS song_count
             FROM events e LEFT JOIN event_songs es ON es.event_id = e.id
             GROUP BY e.id ORDER BY COALESCE(e.planned_at, e.created_at) DESC'
        )->fetchAll();
    }

    public function event(int $id): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM events WHERE id = ?');
        $statement->execute([$id]);
        $event = $statement->fetch();
        if (!$event) {
            return null;
        }

        $songs = $this->db->prepare(
            'SELECT es.*, s.title, s.source_key, s.bpm AS default_bpm, s.notation_profile
             FROM event_songs es JOIN songs s ON s.id = es.song_id
             WHERE es.event_id = ? ORDER BY es.position, es.id'
        );
        $songs->execute([$id]);
        $event['songs'] = $songs->fetchAll();
        return $event;
    }

    public function saveEvent(?int $id, array $data): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Nazwa wydarzenia jest wymagana.');
        }
        $timestamp = now();
        if ($id === null) {
            $statement = $this->db->prepare(
                'INSERT INTO events (name, planned_at, location, status, comment, public_token, live_revision, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)'
            );
            $statement->execute([
                $name,
                $this->nullable($data['planned_at'] ?? null),
                $this->nullable($data['location'] ?? null),
                $this->validStatus((string) ($data['status'] ?? 'draft')),
                $this->nullable($data['comment'] ?? null),
                bin2hex(random_bytes(24)),
                $timestamp,
                $timestamp,
            ]);
            $id = (int) $this->db->lastInsertId();
            $state = $this->db->prepare(
                'INSERT INTO live_states (event_id, event_song_id, current_form_id, next_form_id, revision, updated_at, updated_by)
                 VALUES (?, NULL, NULL, NULL, 1, ?, ?)'
            );
            $state->execute([$id, $timestamp, current_user()['id'] ?? null]);
        } else {
            $statement = $this->db->prepare(
                'UPDATE events SET name = ?, planned_at = ?, location = ?, status = ?, comment = ?, updated_at = ? WHERE id = ?'
            );
            $statement->execute([
                $name,
                $this->nullable($data['planned_at'] ?? null),
                $this->nullable($data['location'] ?? null),
                $this->validStatus((string) ($data['status'] ?? 'draft')),
                $this->nullable($data['comment'] ?? null),
                $timestamp,
                $id,
            ]);
            $this->touchEvent($id);
        }
        $this->log('event', $id, $data['id'] ?? null ? 'updated' : 'created');
        return $id;
    }

    public function addSongToEvent(int $eventId, int $songId): int
    {
        $song = $this->song($songId);
        if ($song === null) {
            throw new RuntimeException('Nie znaleziono pieśni.');
        }
        $positionStatement = $this->db->prepare('SELECT COALESCE(MAX(position), -1) + 1 FROM event_songs WHERE event_id = ?');
        $positionStatement->execute([$eventId]);
        $position = (int) $positionStatement->fetchColumn();

        $this->db->beginTransaction();
        try {
            $insert = $this->db->prepare(
                'INSERT INTO event_songs (event_id, song_id, position, transpose_steps, bpm_override, comment)
                 VALUES (?, ?, ?, 0, NULL, NULL)'
            );
            $insert->execute([$eventId, $songId, $position]);
            $eventSongId = (int) $this->db->lastInsertId();
            $formInsert = $this->db->prepare(
                'INSERT INTO event_song_form_items (event_song_id, section_id, position, transpose_steps, comment)
                 VALUES (?, ?, ?, ?, ?)'
            );
            foreach ($song['form'] as $item) {
                $formInsert->execute([
                    $eventSongId,
                    (int) $item['section_id'],
                    (int) $item['position'],
                    (int) $item['transpose_steps'],
                    $item['comment'],
                ]);
            }
            $this->touchEvent($eventId);
            $this->db->commit();
            return $eventSongId;
        } catch (Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }

    public function eventSong(int $id): ?array
    {
        $statement = $this->db->prepare(
            'SELECT es.*, s.title, s.source_key, s.bpm AS default_bpm, s.notation_profile, e.name AS event_name
             FROM event_songs es
             JOIN songs s ON s.id = es.song_id
             JOIN events e ON e.id = es.event_id
             WHERE es.id = ?'
        );
        $statement->execute([$id]);
        $eventSong = $statement->fetch();
        if (!$eventSong) {
            return null;
        }
        $form = $this->db->prepare(
            'SELECT f.*, s.label AS section_label, s.type AS section_type, s.lyrics, s.chords, s.comment AS section_comment
             FROM event_song_form_items f
             JOIN song_sections s ON s.id = f.section_id
             WHERE f.event_song_id = ? ORDER BY f.position, f.id'
        );
        $form->execute([$id]);
        $eventSong['form'] = $form->fetchAll();

        $sections = $this->db->prepare(
            'SELECT * FROM song_sections WHERE song_id = ? AND archived = 0 ORDER BY position, id'
        );
        $sections->execute([(int) $eventSong['song_id']]);
        $eventSong['available_sections'] = $sections->fetchAll();
        return $eventSong;
    }

    public function saveEventSong(int $id, array $data): void
    {
        $eventSong = $this->eventSong($id);
        if ($eventSong === null) {
            throw new RuntimeException('Nie znaleziono pieśni w wydarzeniu.');
        }
        $form = json_decode((string) ($data['form_json'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($form) || count($form) === 0) {
            throw new RuntimeException('Forma musi zawierać co najmniej jedną część.');
        }
        $allowedSections = array_map('intval', array_column($eventSong['available_sections'], 'id'));

        $this->db->beginTransaction();
        try {
            $update = $this->db->prepare(
                'UPDATE event_songs SET transpose_steps = ?, bpm_override = ?, comment = ? WHERE id = ?'
            );
            $update->execute([
                (int) ($data['transpose_steps'] ?? 0),
                $this->nullableInt($data['bpm_override'] ?? null),
                $this->nullable($data['comment'] ?? null),
                $id,
            ]);
            $delete = $this->db->prepare('DELETE FROM event_song_form_items WHERE event_song_id = ?');
            $delete->execute([$id]);
            $insert = $this->db->prepare(
                'INSERT INTO event_song_form_items (event_song_id, section_id, position, transpose_steps, comment)
                 VALUES (?, ?, ?, ?, ?)'
            );
            foreach (array_values($form) as $position => $item) {
                $sectionId = (int) ($item['sectionId'] ?? 0);
                if (!in_array($sectionId, $allowedSections, true)) {
                    continue;
                }
                $insert->execute([
                    $id,
                    $sectionId,
                    $position,
                    (int) ($item['transpose'] ?? 0),
                    $this->nullable($item['comment'] ?? null),
                ]);
            }
            $this->clearInvalidLiveState((int) $eventSong['event_id']);
            $this->touchEvent((int) $eventSong['event_id']);
            $this->db->commit();
        } catch (Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }

    public function moveEventSong(int $eventSongId, int $direction): void
    {
        $eventSong = $this->eventSong($eventSongId);
        if ($eventSong === null) {
            return;
        }
        $comparison = $direction < 0 ? 'position < ?' : 'position > ?';
        $order = $direction < 0 ? 'DESC' : 'ASC';
        $neighborStatement = $this->db->prepare(
            "SELECT id, position FROM event_songs WHERE event_id = ? AND {$comparison} ORDER BY position {$order} LIMIT 1"
        );
        $neighborStatement->execute([(int) $eventSong['event_id'], (int) $eventSong['position']]);
        $neighbor = $neighborStatement->fetch();
        if (!$neighbor) {
            return;
        }

        $this->db->beginTransaction();
        try {
            $update = $this->db->prepare('UPDATE event_songs SET position = ? WHERE id = ?');
            $update->execute([(int) $neighbor['position'], $eventSongId]);
            $update->execute([(int) $eventSong['position'], (int) $neighbor['id']]);
            $this->touchEvent((int) $eventSong['event_id']);
            $this->db->commit();
        } catch (Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }

    public function removeEventSong(int $eventSongId): int
    {
        $eventSong = $this->eventSong($eventSongId);
        if ($eventSong === null) {
            return 0;
        }
        $eventId = (int) $eventSong['event_id'];
        $statement = $this->db->prepare('DELETE FROM event_songs WHERE id = ?');
        $statement->execute([$eventSongId]);
        $this->clearInvalidLiveState($eventId);
        $this->touchEvent($eventId);
        return $eventId;
    }

    public function directLive(int $eventId, int $formId, int $userId): array
    {
        $lookup = $this->db->prepare(
            'SELECT f.id, f.event_song_id
             FROM event_song_form_items f
             JOIN event_songs es ON es.id = f.event_song_id
             WHERE f.id = ? AND es.event_id = ?'
        );
        $lookup->execute([$formId, $eventId]);
        $target = $lookup->fetch();
        if (!$target) {
            throw new RuntimeException('Ta część nie należy do wydarzenia.');
        }

        $this->db->beginTransaction();
        try {
            $state = $this->liveState($eventId);
            if ((int) ($state['next_form_id'] ?? 0) === $formId) {
                $current = $formId;
                $next = null;
                $eventSongId = (int) $target['event_song_id'];
                $action = 'now';
            } else {
                $current = $state['current_form_id'] !== null ? (int) $state['current_form_id'] : null;
                $next = $formId;
                $eventSongId = $state['event_song_id'] !== null ? (int) $state['event_song_id'] : null;
                $action = 'next';
            }

            $revision = (int) $state['revision'] + 1;
            $update = $this->db->prepare(
                'UPDATE live_states SET event_song_id = ?, current_form_id = ?, next_form_id = ?, revision = ?, updated_at = ?, updated_by = ?
                 WHERE event_id = ?'
            );
            $update->execute([$eventSongId, $current, $next, $revision, now(), $userId, $eventId]);
            $this->touchEvent($eventId, false);
            $this->db->commit();
            return ['action' => $action, 'revision' => $revision];
        } catch (Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }

    public function updateLiveSetting(int $eventId, string $scope, int $id, string $field, mixed $value): void
    {
        $allowed = [
            'event' => ['comment'],
            'song' => ['transpose_steps', 'bpm_override', 'comment'],
            'form' => ['transpose_steps', 'comment'],
        ];
        if (!isset($allowed[$scope]) || !in_array($field, $allowed[$scope], true)) {
            throw new RuntimeException('Niedozwolona zmiana.');
        }

        if ($scope === 'event') {
            if ($id !== $eventId) {
                throw new RuntimeException('Nieprawidłowe wydarzenie.');
            }
            $statement = $this->db->prepare('UPDATE events SET comment = ?, updated_at = ? WHERE id = ?');
            $statement->execute([$this->nullable($value), now(), $eventId]);
        } elseif ($scope === 'song') {
            $check = $this->db->prepare('SELECT id FROM event_songs WHERE id = ? AND event_id = ?');
            $check->execute([$id, $eventId]);
            if (!$check->fetchColumn()) {
                throw new RuntimeException('Pieśń nie należy do wydarzenia.');
            }
            $safeValue = $field === 'comment' ? $this->nullable($value) : ($field === 'bpm_override' ? $this->nullableInt($value) : (int) $value);
            $statement = $this->db->prepare("UPDATE event_songs SET {$field} = ? WHERE id = ?");
            $statement->execute([$safeValue, $id]);
        } else {
            $check = $this->db->prepare(
                'SELECT f.id FROM event_song_form_items f JOIN event_songs es ON es.id = f.event_song_id
                 WHERE f.id = ? AND es.event_id = ?'
            );
            $check->execute([$id, $eventId]);
            if (!$check->fetchColumn()) {
                throw new RuntimeException('Część nie należy do wydarzenia.');
            }
            $safeValue = $field === 'comment' ? $this->nullable($value) : (int) $value;
            $statement = $this->db->prepare("UPDATE event_song_form_items SET {$field} = ? WHERE id = ?");
            $statement->execute([$safeValue, $id]);
        }
        $this->touchEvent($eventId);
    }

    public function liveSnapshot(int $eventId, string $outputProfile): ?array
    {
        $event = $this->event($eventId);
        if ($event === null) {
            return null;
        }
        $state = $this->liveState($eventId);
        $songs = [];
        foreach ($event['songs'] as $eventSong) {
            $details = $this->eventSong((int) $eventSong['id']);
            if ($details === null) {
                continue;
            }
            $items = [];
            foreach ($details['form'] as $item) {
                $steps = (int) $details['transpose_steps'] + (int) $item['transpose_steps'];
                $items[] = [
                    'id' => (int) $item['id'],
                    'section_id' => (int) $item['section_id'],
                    'label' => $item['section_label'],
                    'type' => $item['section_type'],
                    'lyrics' => $item['lyrics'],
                    'chords' => Chord::transposeLines((string) $item['chords'], $steps, (string) $details['notation_profile'], $outputProfile),
                    'comment' => $item['comment'] ?: $item['section_comment'],
                    'transpose_steps' => (int) $item['transpose_steps'],
                    'effective_transpose' => $steps,
                ];
            }
            $songs[] = [
                'id' => (int) $details['id'],
                'song_id' => (int) $details['song_id'],
                'title' => $details['title'],
                'source_key' => $details['source_key'],
                'transpose_steps' => (int) $details['transpose_steps'],
                'bpm' => $details['bpm_override'] !== null ? (int) $details['bpm_override'] : ($details['default_bpm'] !== null ? (int) $details['default_bpm'] : null),
                'comment' => $details['comment'],
                'form' => $items,
            ];
        }

        return [
            'event' => [
                'id' => (int) $event['id'],
                'name' => $event['name'],
                'planned_at' => $event['planned_at'],
                'location' => $event['location'],
                'status' => $event['status'],
                'comment' => $event['comment'],
                'public_token' => $event['public_token'],
            ],
            'state' => [
                'event_song_id' => $state['event_song_id'] !== null ? (int) $state['event_song_id'] : null,
                'current_form_id' => $state['current_form_id'] !== null ? (int) $state['current_form_id'] : null,
                'next_form_id' => $state['next_form_id'] !== null ? (int) $state['next_form_id'] : null,
                'revision' => (int) $state['revision'],
                'updated_at' => $state['updated_at'],
            ],
            'revision' => max((int) $event['live_revision'], (int) $state['revision']),
            'songs' => $songs,
        ];
    }

    public function eventByToken(string $token): ?array
    {
        $statement = $this->db->prepare('SELECT id FROM events WHERE public_token = ?');
        $statement->execute([$token]);
        $id = $statement->fetchColumn();
        return $id ? $this->event((int) $id) : null;
    }

    /**
     * Importuje pieśni, których tytułów nie ma jeszcze w bibliotece.
     *
     * @return array{imported: int, skipped: int}
     */
    public function importSongs(array $songs): array
    {
        $existingTitles = [];
        foreach ($this->songs(true) as $existingSong) {
            $existingTitles[trim((string) $existingSong['title'])] = true;
        }

        $result = ['imported' => 0, 'skipped' => 0];
        foreach ($songs as $song) {
            if (!is_array($song)) {
                throw new RuntimeException('Nieprawidłowy rekord pieśni w pliku importu.');
            }

            $title = trim((string) ($song['title'] ?? ''));
            if ($title === '' || !isset($song['sections']) || !is_array($song['sections'])) {
                throw new RuntimeException('Pieśń w pliku importu nie ma tytułu lub części.');
            }
            if (isset($existingTitles[$title])) {
                $result['skipped']++;
                continue;
            }

            $this->saveStructuredSong($song);
            $existingTitles[$title] = true;
            $result['imported']++;
        }

        return $result;
    }

    /** @return array{imported: int, skipped: int} */
    public function seedSongbook(): array
    {
        $path = dirname(__DIR__) . '/data/songbook.json';
        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException('Nie można odczytać dołączonego śpiewnika.');
        }

        $songs = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($songs)) {
            throw new RuntimeException('Dołączony śpiewnik ma nieprawidłowy format.');
        }

        return $this->importSongs($songs);
    }

    /**
     * Zachowane dla zgodności ze starszymi instalacjami i testami.
     * @return array{imported: int, skipped: int}
     */
    public function seedDemoSongs(): array
    {
        return $this->seedSongbook();
    }

    private function saveStructuredSong(array $song): void
    {
        $sections = [];
        $form = [];
        foreach ($song['sections'] as $index => $section) {
            $key = 'seed-' . $index;
            $sections[] = ['key' => $key] + $section;
            $form[] = ['sectionKey' => $key, 'transpose' => 0, 'comment' => ''];
        }
        if (isset($song['form']) && is_array($song['form'])) {
            $form = array_map(
                fn (int $sectionIndex): array => ['sectionKey' => 'seed-' . $sectionIndex, 'transpose' => 0, 'comment' => ''],
                $song['form']
            );
        }
        $this->saveSong(null, $song + [
            'sections_json' => json_encode($sections, JSON_UNESCAPED_UNICODE),
            'form_json' => json_encode($form, JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function liveState(int $eventId): array
    {
        $statement = $this->db->prepare('SELECT * FROM live_states WHERE event_id = ?');
        $statement->execute([$eventId]);
        $state = $statement->fetch();
        if ($state) {
            return $state;
        }
        $insert = $this->db->prepare(
            'INSERT INTO live_states (event_id, event_song_id, current_form_id, next_form_id, revision, updated_at, updated_by)
             VALUES (?, NULL, NULL, NULL, 1, ?, NULL)'
        );
        $insert->execute([$eventId, now()]);
        return [
            'event_id' => $eventId,
            'event_song_id' => null,
            'current_form_id' => null,
            'next_form_id' => null,
            'revision' => 1,
            'updated_at' => now(),
        ];
    }

    private function touchEvent(int $eventId, bool $touchState = true): void
    {
        $statement = $this->db->prepare('UPDATE events SET live_revision = live_revision + 1, updated_at = ? WHERE id = ?');
        $statement->execute([now(), $eventId]);
        if ($touchState) {
            $state = $this->db->prepare('UPDATE live_states SET revision = revision + 1, updated_at = ? WHERE event_id = ?');
            $state->execute([now(), $eventId]);
        }
    }

    private function clearInvalidLiveState(int $eventId): void
    {
        $statement = $this->db->prepare(
            'UPDATE live_states SET current_form_id = NULL, next_form_id = NULL, event_song_id = NULL
             WHERE event_id = ? AND (
                 current_form_id IS NOT NULL AND current_form_id NOT IN (
                     SELECT f.id FROM event_song_form_items f JOIN event_songs es ON es.id = f.event_song_id WHERE es.event_id = ?
                 ) OR next_form_id IS NOT NULL AND next_form_id NOT IN (
                     SELECT f.id FROM event_song_form_items f JOIN event_songs es ON es.id = f.event_song_id WHERE es.event_id = ?
                 )
             )'
        );
        $statement->execute([$eventId, $eventId, $eventId]);
    }

    private function log(string $entityType, int $entityId, string $action): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO change_log (user_id, entity_type, entity_id, action, created_at) VALUES (?, ?, ?, ?, ?)'
        );
        $statement->execute([current_user()['id'] ?? null, $entityType, $entityId, $action, now()]);
    }

    private function publicUser(array $user): array
    {
        unset($user['password_hash']);
        $user['id'] = (int) $user['id'];
        return $user;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function validStatus(string $status): string
    {
        return in_array($status, ['draft', 'ready', 'live', 'finished', 'archived'], true) ? $status : 'draft';
    }
}
