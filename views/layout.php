<?php

use BandBook\Chord;

$pageTitle = isset($title) ? $title . ' · BandBook' : 'BandBook';
$user = current_user();
$flash = pull_flash();
?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#10251f">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="assets/app.css">
    <script defer src="assets/app.js"></script>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<?php if ($user): ?>
    <header class="topbar">
        <a class="brand" href="<?= e(url()) ?>" aria-label="BandBook — pulpit">
            <span class="brand-mark">B</span>
            <span>BandBook</span>
        </a>
        <nav class="main-nav" aria-label="Główna nawigacja">
            <a class="<?= ($active ?? '') === 'dashboard' ? 'active' : '' ?>" href="<?= e(url()) ?>">Pulpit</a>
            <a class="<?= ($active ?? '') === 'songs' ? 'active' : '' ?>" href="<?= e(url('songs')) ?>">Pieśni</a>
            <a class="<?= ($active ?? '') === 'events' ? 'active' : '' ?>" href="<?= e(url('events')) ?>">Wydarzenia</a>
        </nav>
        <details class="user-menu">
            <summary><?= e($user['display_name']) ?><span class="avatar"><?= e(strtoupper(substr($user['display_name'], 0, 1))) ?></span></summary>
            <div class="user-popover">
                <a href="<?= e(url('settings')) ?>">Notacja: <?= e(Chord::label($user['notation_profile'])) ?></a>
                <form method="post" action="<?= e(url('logout')) ?>">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <button class="link-button" type="submit">Wyloguj się</button>
                </form>
            </div>
        </details>
    </header>
<?php endif; ?>

<?php if ($flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
<?php endif; ?>

<main class="<?= e($mainClass ?? 'app-shell') ?>">
    <?= $content ?>
</main>
</body>
</html>
