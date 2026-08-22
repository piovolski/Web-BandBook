<?php

declare(strict_types=1);

use BandBook\Repository;
use BandBook\View;

require dirname(__DIR__) . '/src/bootstrap.php';

$repo = new Repository($db);
$route = (string) ($_GET['route'] ?? 'dashboard');
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if (!$repo->hasUsers() && $route !== 'setup') {
    redirect('setup');
}

try {
    if (in_array($route, ['screen', 'api-screen', 'obs', 'api-obs'], true)) {
        $isObsRoute = in_array($route, ['obs', 'api-obs'], true);
        $snapshot = $repo->latestAudienceSnapshot('pl') ?? [
            'event' => [
                'id' => null,
                'name' => 'Stały ekran uczestników',
                'planned_at' => null,
                'location' => null,
                'status' => 'ready',
                'comment' => null,
                'background_image' => null,
                'public_token' => null,
            ],
            'state' => [
                'event_song_id' => null,
                'current_form_id' => null,
                'next_form_id' => null,
                'output_mode' => 'blackout',
                'audience_font_scale' => 100,
                'obs_font_scale' => 100,
                'obs_bar_opacity' => 85,
                'current_sequence' => 0,
                'revision' => 0,
                'updated_at' => null,
            ],
            'revision' => 0,
            'songs' => [],
            'screen_cursor' => 'none',
        ];
        if (in_array($route, ['api-screen', 'api-obs'], true)) {
            $cursor = (string) ($_GET['cursor'] ?? '');
            if ($cursor !== '' && $cursor === (string) $snapshot['screen_cursor']) {
                json_response(['unchanged' => true, 'cursor' => $snapshot['screen_cursor']]);
            }
            json_response(['unchanged' => false, 'snapshot' => $snapshot]);
        }
        if ($isObsRoute) {
            View::render('overlay', [
                'title' => 'Stała nakładka OBS',
                'snapshot' => $snapshot,
                'globalOverlay' => true,
                'mainClass' => 'overlay-page',
                'bodyClass' => 'overlay-body',
            ], 'projection_layout');
        } else {
            View::render('public', [
                'title' => 'Stały ekran uczestników',
                'snapshot' => $snapshot,
                'globalAudience' => true,
                'mainClass' => 'audience-page',
                'bodyClass' => 'audience-body',
            ], 'projection_layout');
        }
        exit;
    }

    if ($route === 'setup') {
        if ($repo->hasUsers()) {
            redirect(current_user() ? 'dashboard' : 'login');
        }
        $error = null;
        if ($method === 'POST') {
            verify_csrf();
            $password = (string) ($_POST['password'] ?? '');
            if (strlen($password) < 10) {
                $error = 'Hasło musi mieć co najmniej 10 znaków.';
            } elseif (trim((string) ($_POST['username'] ?? '')) === '' || trim((string) ($_POST['display_name'] ?? '')) === '') {
                $error = 'Uzupełnij nazwę i login.';
            } else {
                $id = $repo->createAdmin(
                    (string) $_POST['username'],
                    (string) $_POST['display_name'],
                    $password,
                    (string) ($_POST['notation_profile'] ?? 'pl')
                );
                $_SESSION['user'] = $repo->user($id);
                if (!empty($_POST['demo'])) {
                    $repo->seedSongbook();
                }
                flash('BandBook jest gotowy. Możesz utworzyć pierwsze wydarzenie.');
                redirect('dashboard');
            }
        }
        View::render('setup', compact('error') + ['title' => 'Pierwsze uruchomienie'], 'auth_layout');
        exit;
    }

    if ($route === 'login') {
        if (current_user()) {
            redirect('dashboard');
        }
        $error = null;
        if ($method === 'POST') {
            verify_csrf();
            $user = $repo->authenticate((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''));
            if ($user) {
                session_regenerate_id(true);
                $_SESSION['user'] = $user;
                redirect('dashboard');
            }
            $error = 'Nieprawidłowy login lub hasło.';
        }
        View::render('login', compact('error') + ['title' => 'Logowanie'], 'auth_layout');
        exit;
    }

    if (in_array($route, ['public', 'overlay', 'api-public', 'event-background'], true)) {
        $token = (string) ($_GET['token'] ?? '');
        $event = $repo->eventByToken($token);
        if ($event === null) {
            http_response_code(404);
            View::render('error', ['title' => 'Nie znaleziono', 'heading' => 'Ten widok nie jest dostępny', 'message' => 'Link jest nieprawidłowy albo został wyłączony.', 'mainClass' => 'audience-page']);
            exit;
        }
        if ($route === 'event-background') {
            serve_event_background($event['background_image'] ?? null);
        }
        $snapshot = $repo->liveSnapshot((int) $event['id'], 'pl');
        if ($route === 'api-public') {
            $since = (int) ($_GET['since'] ?? 0);
            if ($since >= (int) $snapshot['revision']) {
                json_response(['unchanged' => true, 'revision' => (int) $snapshot['revision']]);
            }
            json_response(['unchanged' => false, 'snapshot' => $snapshot]);
        }
        if ($route === 'overlay') {
            View::render('overlay', ['title' => 'OBS — ' . $event['name'], 'snapshot' => $snapshot, 'mainClass' => 'overlay-page', 'bodyClass' => 'overlay-body'], 'projection_layout');
        } else {
            View::render('public', ['title' => $event['name'], 'snapshot' => $snapshot, 'mainClass' => 'audience-page', 'bodyClass' => 'audience-body'], 'projection_layout');
        }
        exit;
    }

    $user = require_auth();

    if ($route === 'logout' && $method === 'POST') {
        verify_csrf();
        $_SESSION = [];
        session_destroy();
        redirect('login');
    }

    if ($route === 'dashboard') {
        $allEvents = $repo->events();
        $stats = [
            'songs' => count($repo->songs()),
            'events' => count($allEvents),
            'ready' => count(array_filter($allEvents, fn (array $event): bool => in_array($event['status'], ['ready', 'live'], true))),
        ];
        $events = array_slice($allEvents, 0, 4);
        View::render('dashboard', compact('stats', 'events', 'user') + ['title' => 'Pulpit', 'active' => 'dashboard']);
        exit;
    }

    if ($route === 'songs') {
        View::render('songs/index', ['title' => 'Pieśni', 'active' => 'songs', 'songs' => $repo->songs()]);
        exit;
    }

    if ($route === 'song-new' || $route === 'song-edit') {
        $id = $route === 'song-edit' ? (int) ($_GET['id'] ?? 0) : null;
        $song = $id ? $repo->song($id) : ['notation_profile' => $user['notation_profile'], 'sections' => [], 'form' => []];
        if ($id && $song === null) {
            throw new RuntimeException('Nie znaleziono pieśni.');
        }
        $error = null;
        if ($method === 'POST') {
            verify_csrf();
            try {
                $savedId = $repo->saveSong($id, $_POST + ['id' => $id]);
                flash($id ? 'Pieśń została zaktualizowana.' : 'Pieśń została dodana.');
                redirect('song-edit', ['id' => $savedId]);
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
                $song = array_merge($song ?: [], $_POST);
            }
        }
        $categories = $repo->categories(true);
        View::render('songs/editor', compact('song', 'error', 'categories') + ['title' => $id ? 'Edycja pieśni' : 'Nowa pieśń', 'active' => 'songs']);
        exit;
    }

    if ($route === 'song-archive' && $method === 'POST') {
        verify_csrf();
        $repo->archiveSong((int) ($_GET['id'] ?? 0));
        flash('Pieśń została przeniesiona do archiwum.');
        redirect('songs');
    }

    if ($route === 'events') {
        View::render('events/index', ['title' => 'Wydarzenia', 'active' => 'events', 'events' => $repo->events()]);
        exit;
    }

    if ($route === 'event-new' || $route === 'event-edit') {
        $id = $route === 'event-edit' ? (int) ($_GET['id'] ?? 0) : null;
        $event = $id ? $repo->event($id) : ['status' => 'draft'];
        if ($id && $event === null) {
            throw new RuntimeException('Nie znaleziono wydarzenia.');
        }
        $error = null;
        if ($method === 'POST') {
            verify_csrf();
            $oldBackground = $event['background_image'] ?? null;
            $newUpload = null;
            try {
                $backgroundImage = !empty($_POST['clear_background']) ? null : $oldBackground;
                $newUpload = store_event_background($_FILES['background_image'] ?? null);
                if ($newUpload !== null) {
                    $backgroundImage = $newUpload;
                }
                $savedId = $repo->saveEvent($id, $_POST + ['id' => $id, 'background_image' => $backgroundImage]);
                if ($oldBackground && $oldBackground !== $backgroundImage) {
                    delete_event_background((string) $oldBackground);
                }
                flash($id ? 'Wydarzenie zostało zaktualizowane.' : 'Wydarzenie zostało utworzone.');
                redirect('event', ['id' => $savedId]);
            } catch (Throwable $exception) {
                if ($newUpload !== null && $newUpload !== $oldBackground) {
                    delete_event_background($newUpload);
                }
                $error = $exception->getMessage();
                $event = array_merge($event ?: [], $_POST);
            }
        }
        View::render('events/editor', compact('event', 'error') + ['title' => $id ? 'Edycja wydarzenia' : 'Nowe wydarzenie', 'active' => 'events']);
        exit;
    }

    if ($route === 'event') {
        $event = $repo->event((int) ($_GET['id'] ?? 0));
        if ($event === null) {
            throw new RuntimeException('Nie znaleziono wydarzenia.');
        }
        $browserSongs = $repo->songBrowser((int) $event['id']);
        $categories = $repo->categories();
        View::render('events/detail', compact('event', 'browserSongs', 'categories') + ['title' => $event['name'], 'active' => 'events', 'mainClass' => 'app-shell planner-shell']);
        exit;
    }

    if ($route === 'api-song-preview') {
        $song = $repo->songPreview((int) ($_GET['id'] ?? 0));
        if ($song === null) {
            json_response(['error' => 'Nie znaleziono pieśni.'], 404);
        }
        json_response(['song' => $song]);
    }

    if ($route === 'api-event-song-add' && $method === 'POST') {
        verify_csrf();
        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }
        $eventId = (int) ($_GET['id'] ?? 0);
        $event = $repo->event($eventId);
        if ($event === null) {
            json_response(['error' => 'Nie znaleziono wydarzenia.'], 404);
        }
        $songId = (int) ($payload['song_id'] ?? 0);
        $alreadyUsed = count(array_filter(
            $event['songs'],
            static fn (array $eventSong): bool => (int) $eventSong['song_id'] === $songId
        ));
        if ($alreadyUsed > 0 && empty($payload['allow_duplicate'])) {
            json_response(['error' => 'Ta pieśń jest już w repertuarze.', 'duplicate' => true], 409);
        }

        $eventSongId = $repo->addSongToEvent($eventId, $songId);
        $event = $repo->event($eventId);
        $eventSong = $repo->eventSong($eventSongId);
        if ($event === null || $eventSong === null) {
            json_response(['error' => 'Nie udało się odczytać dodanej pozycji.'], 500);
        }
        $song = $eventSong;
        $index = count($event['songs']) - 1;
        $total = count($event['songs']);
        ob_start();
        require dirname(__DIR__) . '/views/events/repertoire_item.php';
        $html = (string) ob_get_clean();
        json_response(['added' => true, 'event_song_id' => $eventSongId, 'count' => $total, 'html' => $html]);
    }

    if ($route === 'event-song-add' && $method === 'POST') {
        verify_csrf();
        $eventId = (int) ($_GET['id'] ?? 0);
        $repo->addSongToEvent($eventId, (int) ($_POST['song_id'] ?? 0));
        flash('Pieśń została dodana do repertuaru.');
        redirect('event', ['id' => $eventId]);
    }

    if ($route === 'event-song-edit') {
        $id = (int) ($_GET['id'] ?? 0);
        $eventSong = $repo->eventSong($id);
        if ($eventSong === null) {
            throw new RuntimeException('Nie znaleziono tej pozycji repertuaru.');
        }
        $error = null;
        if ($method === 'POST') {
            verify_csrf();
            try {
                $repo->saveEventSong($id, $_POST);
                flash('Ustawienia wykonania zostały zapisane.');
                redirect('event-song-edit', ['id' => $id]);
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }
        View::render('events/song_editor', compact('eventSong', 'error') + ['title' => $eventSong['title'], 'active' => 'events']);
        exit;
    }

    if ($route === 'event-song-move' && $method === 'POST') {
        verify_csrf();
        $id = (int) ($_GET['id'] ?? 0);
        $eventSong = $repo->eventSong($id);
        if ($eventSong === null) {
            throw new RuntimeException('Nie znaleziono pozycji repertuaru.');
        }
        $repo->moveEventSong($id, (int) ($_GET['direction'] ?? 1));
        redirect('event', ['id' => $eventSong['event_id']]);
    }

    if ($route === 'api-event-song-reorder' && $method === 'POST') {
        verify_csrf();
        $eventId = (int) ($_GET['id'] ?? 0);
        if ($repo->event($eventId) === null) {
            json_response(['error' => 'Nie znaleziono wydarzenia.'], 404);
        }
        $payload = request_json();
        $eventSongIds = $payload['event_song_ids'] ?? null;
        if (!is_array($eventSongIds)) {
            json_response(['error' => 'Nieprawidłowa kolejność repertuaru.'], 422);
        }
        try {
            $repo->reorderEventSongs($eventId, $eventSongIds);
        } catch (RuntimeException $exception) {
            json_response(['error' => $exception->getMessage()], 422);
        }
        json_response(['saved' => true, 'event_song_ids' => array_values(array_map('intval', $eventSongIds))]);
    }

    if ($route === 'event-song-remove' && $method === 'POST') {
        verify_csrf();
        $eventId = $repo->removeEventSong((int) ($_GET['id'] ?? 0));
        flash('Pieśń została usunięta z repertuaru.');
        redirect('event', ['id' => $eventId]);
    }

    if ($route === 'live') {
        $eventId = (int) ($_GET['id'] ?? 0);
        $snapshot = $repo->liveSnapshot($eventId, (string) $user['notation_profile']);
        if ($snapshot === null) {
            throw new RuntimeException('Nie znaleziono wydarzenia.');
        }
        View::render('live', compact('snapshot', 'user') + ['title' => 'Live — ' . $snapshot['event']['name'], 'mainClass' => 'live-page']);
        exit;
    }

    if ($route === 'api-live') {
        $eventId = (int) ($_GET['id'] ?? 0);
        $profile = ($_GET['profile'] ?? $user['notation_profile']) === 'intl' ? 'intl' : 'pl';
        $snapshot = $repo->liveSnapshot($eventId, $profile);
        if ($snapshot === null) {
            json_response(['error' => 'Nie znaleziono wydarzenia.'], 404);
        }
        $since = (int) ($_GET['since'] ?? 0);
        if ($since >= (int) $snapshot['revision']) {
            json_response(['unchanged' => true, 'revision' => (int) $snapshot['revision']]);
        }
        json_response(['unchanged' => false, 'snapshot' => $snapshot]);
    }

    if ($route === 'api-live-action' && $method === 'POST') {
        verify_csrf();
        $payload = request_json();
        $eventId = (int) ($_GET['id'] ?? 0);
        $result = $repo->directLive($eventId, (int) ($payload['form_id'] ?? 0), (int) $user['id']);
        json_response(['ok' => true] + $result);
    }

    if ($route === 'api-live-output' && $method === 'POST') {
        verify_csrf();
        $payload = request_json();
        $eventId = (int) ($_GET['id'] ?? 0);
        if (array_key_exists('mode', $payload)) {
            $repo->setAudienceMode($eventId, (string) $payload['mode'], (int) $user['id']);
        } elseif (array_key_exists('font_scale', $payload)) {
            $repo->setAudienceFontScale($eventId, (int) $payload['font_scale'], (int) $user['id']);
        } else {
            throw new RuntimeException('Brak ustawienia ekranu uczestników.');
        }
        json_response(['ok' => true]);
    }

    if ($route === 'api-live-obs' && $method === 'POST') {
        verify_csrf();
        $payload = request_json();
        $eventId = (int) ($_GET['id'] ?? 0);
        $repo->setObsSetting(
            $eventId,
            (string) ($payload['field'] ?? ''),
            (int) ($payload['value'] ?? 0),
            (int) $user['id']
        );
        json_response(['ok' => true]);
    }

    if ($route === 'api-live-setting' && $method === 'POST') {
        verify_csrf();
        $payload = request_json();
        $eventId = (int) ($_GET['id'] ?? 0);
        $repo->updateLiveSetting(
            $eventId,
            (string) ($payload['scope'] ?? ''),
            (int) ($payload['id'] ?? 0),
            (string) ($payload['field'] ?? ''),
            $payload['value'] ?? null
        );
        json_response(['ok' => true]);
    }

    if ($route === 'api-live-part' && $method === 'POST') {
        verify_csrf();
        $payload = request_json();
        $eventId = (int) ($_GET['id'] ?? 0);
        $repo->updateLivePartContent(
            $eventId,
            (int) ($payload['form_id'] ?? 0),
            [
                'label' => $payload['label'] ?? null,
                'lyrics' => $payload['lyrics'] ?? '',
                'chords' => $payload['chords'] ?? '',
                'save_to_source' => (bool) ($payload['save_to_source'] ?? false),
            ]
        );
        json_response(['ok' => true]);
    }

    if ($route === 'settings') {
        if ($method === 'POST') {
            verify_csrf();
            $repo->updateNotation((int) $user['id'], (string) ($_POST['notation_profile'] ?? 'pl'));
            $_SESSION['user'] = $repo->user((int) $user['id']);
            flash('Preferencja chwytów została zapisana.');
            redirect('settings');
        }
        View::render('settings', ['title' => 'Preferencje', 'user' => $user]);
        exit;
    }

    http_response_code(404);
    View::render('error', ['title' => 'Nie znaleziono', 'heading' => 'Nie znaleziono strony']);
} catch (Throwable $exception) {
    http_response_code(500);
    View::render('error', [
        'title' => 'Błąd',
        'heading' => 'Nie udało się wykonać tej operacji',
        'message' => $exception->getMessage(),
    ]);
}

function request_json(): array
{
    $payload = json_decode((string) file_get_contents('php://input'), true);
    return is_array($payload) ? $payload : [];
}

function event_background_directory(): string
{
    return dirname(__DIR__) . '/storage/backgrounds';
}

function store_event_background(?array $file): ?string
{
    if (!$file || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Nie udało się przesłać zdjęcia tła.');
    }
    if ((int) ($file['size'] ?? 0) <= 0 || (int) $file['size'] > 12 * 1024 * 1024) {
        throw new RuntimeException('Zdjęcie tła może mieć maksymalnie 12 MB.');
    }
    $temporaryPath = (string) ($file['tmp_name'] ?? '');
    $imageInfo = @getimagesize($temporaryPath);
    $mime = is_array($imageInfo) ? ($imageInfo['mime'] ?? '') : '';
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) {
        throw new RuntimeException('Tło musi być obrazem JPEG, PNG lub WebP.');
    }
    if ((int) ($imageInfo[0] ?? 0) > 12000 || (int) ($imageInfo[1] ?? 0) > 12000) {
        throw new RuntimeException('Zdjęcie tła ma zbyt dużą rozdzielczość.');
    }
    $directory = event_background_directory();
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Nie można utworzyć katalogu na tła wydarzeń.');
    }
    $filename = bin2hex(random_bytes(20)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($temporaryPath, $directory . '/' . $filename)) {
        throw new RuntimeException('Nie udało się zapisać zdjęcia tła.');
    }
    return $filename;
}

function delete_event_background(string $filename): void
{
    if ($filename === '' || basename($filename) !== $filename) {
        return;
    }
    $path = event_background_directory() . '/' . $filename;
    if (is_file($path)) {
        @unlink($path);
    }
}

function serve_event_background(mixed $filename): never
{
    $filename = is_string($filename) ? $filename : '';
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    if ($filename === '' || basename($filename) !== $filename || !isset($types[$extension])) {
        http_response_code(404);
        exit;
    }
    $path = event_background_directory() . '/' . $filename;
    if (!is_file($path)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: ' . $types[$extension]);
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: public, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}
