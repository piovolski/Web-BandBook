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

    if ($route === 'public' || $route === 'overlay' || $route === 'api-public') {
        $token = (string) ($_GET['token'] ?? '');
        $event = $repo->eventByToken($token);
        if ($event === null) {
            http_response_code(404);
            View::render('error', ['title' => 'Nie znaleziono', 'heading' => 'Ten widok nie jest dostępny', 'message' => 'Link jest nieprawidłowy albo został wyłączony.', 'mainClass' => 'audience-page']);
            exit;
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
            View::render('overlay', ['title' => 'OBS — ' . $event['name'], 'snapshot' => $snapshot, 'mainClass' => 'overlay-page', 'bodyClass' => 'overlay-body']);
        } else {
            View::render('public', ['title' => $event['name'], 'snapshot' => $snapshot, 'mainClass' => 'audience-page']);
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
        View::render('songs/editor', compact('song', 'error') + ['title' => $id ? 'Edycja pieśni' : 'Nowa pieśń', 'active' => 'songs']);
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
            try {
                $savedId = $repo->saveEvent($id, $_POST + ['id' => $id]);
                flash($id ? 'Wydarzenie zostało zaktualizowane.' : 'Wydarzenie zostało utworzone.');
                redirect('event', ['id' => $savedId]);
            } catch (Throwable $exception) {
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
        $availableSongs = $repo->songs();
        View::render('events/detail', compact('event', 'availableSongs') + ['title' => $event['name'], 'active' => 'events']);
        exit;
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
