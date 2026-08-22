<?php $pageTitle = isset($title) ? $title . ' · BandBook' : 'BandBook'; ?>
<!doctype html>
<html lang="pl" class="<?= e($bodyClass ?? '') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#000000">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="assets/app.css">
    <script defer src="assets/app.js"></script>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<main class="<?= e($mainClass ?? '') ?>"><?= $content ?></main>
</body>
</html>
