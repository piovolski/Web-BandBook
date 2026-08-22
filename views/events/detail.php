<?php

use BandBook\View;

$categoryGroups = [];
foreach ($categories as $category) {
    $categoryGroups[$category['group_name']][] = $category;
}
$groupLabels = [
    'section' => 'Działy guanelliańskie',
    'songbook' => 'Śpiewniki OpenLP',
    'source' => 'Źródło',
    'liturgical_moment' => 'Moment liturgii',
    'season' => 'Okres liturgiczny',
    'theme' => 'Temat',
    'custom' => 'Własne kategorie',
];
?>
<section class="event-hero">
    <div>
        <a class="back-link" href="<?= e(url('events')) ?>">← Wydarzenia</a>
        <div class="event-title-line"><span class="status status-<?= e($event['status']) ?>"><?= e(View::statusLabel($event['status'])) ?></span><h1><?= e($event['name']) ?></h1></div>
        <p class="lead"><?= e(View::eventDate($event['planned_at'])) ?><?= $event['location'] ? ' · ' . e($event['location']) : '' ?></p>
    </div>
    <div class="button-group"><a class="button button-soft" href="<?= e(url('event-edit', ['id' => $event['id']])) ?>">Ustawienia</a><a class="button button-primary" href="<?= e(url('live', ['id' => $event['id']])) ?>">Otwórz widok live →</a></div>
</section>

<?php if ($event['comment']): ?><aside class="repertoire-note"><strong>Komentarz do wydarzenia</strong><p><?= nl2br(e($event['comment'])) ?></p></aside><?php endif; ?>

<div class="planner-layout" data-song-browser
     data-preview-api="<?= e(url('api-song-preview')) ?>"
     data-add-api="<?= e(url('api-event-song-add', ['id' => $event['id']])) ?>"
     data-song-edit-url="<?= e(url('song-edit')) ?>"
     data-csrf="<?= e(csrf_token()) ?>">
    <section class="song-browser-panel">
        <header class="browser-heading">
            <div><p class="eyebrow">Biblioteka</p><h2>Dobierz pieśni</h2><p>Filtruj, oglądaj pełną formę i dodawaj bez opuszczania wydarzenia.</p></div>
            <label class="browser-search"><span aria-hidden="true">⌕</span><input type="search" placeholder="Tytuł, tekst, autor lub kategoria…" data-browser-search autocomplete="off"></label>
        </header>

        <div class="browser-workspace">
            <aside class="browser-filters" data-browser-filters>
                <div class="filter-section">
                    <p class="filter-title">Widok</p>
                    <button class="category-filter active" type="button" data-category="all"><span>Wszystkie pieśni</span><strong><?= count($browserSongs) ?></strong></button>
                    <button class="category-filter" type="button" data-category="with-chords"><span>Posiadają chwyty</span></button>
                    <button class="category-filter" type="button" data-category="without-chords"><span>Tylko tekst</span></button>
                </div>
                <?php foreach ($categoryGroups as $group => $items): ?>
                    <div class="filter-section">
                        <p class="filter-title"><?= e($groupLabels[$group] ?? $group) ?></p>
                        <?php foreach ($items as $category): ?>
                            <button class="category-filter" type="button" data-category="<?= (int) $category['id'] ?>"><span><?= e($category['name']) ?></span><strong><?= (int) $category['song_count'] ?></strong></button>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </aside>

            <div class="browser-content">
                <div class="browser-result-head"><strong data-browser-result-count>Pieśni</strong><span>Kliknij tytuł, aby zobaczyć tekst i formę</span></div>
                <div class="browser-results" data-browser-results></div>
                <div class="browser-more" data-browser-more hidden></div>
            </div>

            <aside class="song-preview" data-song-preview>
                <div class="preview-placeholder"><span>♪</span><h3>Wybierz pieśń</h3><p>Zobaczysz tutaj tekst, chwyty, komentarze i domyślną formę.</p></div>
            </aside>
        </div>
        <script type="application/json" data-song-browser-data><?= json_encode($browserSongs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    </section>

    <aside class="planner-sidebar">
        <section class="panel repertoire-panel">
            <div class="section-heading"><div><p class="eyebrow">Kolejność</p><h2>Repertuar</h2></div><span class="count-badge" data-repertoire-count><?= count($event['songs']) ?></span></div>
            <div class="empty-state compact" data-repertoire-empty <?= $event['songs'] ? 'hidden' : '' ?>><span class="empty-icon">+</span><h3>Dodaj pierwszą pieśń</h3><p>Wybierz ją z przeglądarki.</p></div>
            <div class="repertoire-list" data-repertoire-list>
                <?php $total = count($event['songs']); ?>
                <?php foreach ($event['songs'] as $index => $song): ?>
                    <?php require __DIR__ . '/repertoire_item.php'; ?>
                <?php endforeach; ?>
            </div>
        </section>

        <article class="panel share-card"><p class="eyebrow">Dla uczestników</p><h2>Publiczny widok</h2><p class="muted">Udostępnij tekst aktualnie granej części.</p><a class="button button-soft button-wide" target="_blank" rel="noopener" href="<?= e(url('public', ['token' => $event['public_token']])) ?>">Otwórz widok uczestnika ↗</a><button class="button button-ghost button-wide" type="button" data-copy-value="<?= e(url('public', ['token' => $event['public_token']])) ?>">Kopiuj adres</button><hr><p class="eyebrow">Transmisja</p><a class="button button-soft button-wide" target="_blank" rel="noopener" href="<?= e(url('overlay', ['token' => $event['public_token']])) ?>">Podgląd nakładki OBS ↗</a><button class="button button-ghost button-wide" type="button" data-copy-value="<?= e(url('overlay', ['token' => $event['public_token']])) ?>">Kopiuj adres OBS</button></article>
    </aside>
</div>
