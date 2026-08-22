<?php $pageTitle = isset($title) ? $title . ' · BandBook' : 'BandBook'; ?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#10251f">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body class="auth-body">
<main class="auth-shell"><?= $content ?></main>
</body>
</html>
