<section class="page-heading">
    <div><p class="eyebrow">Biblioteka</p><h1>Pieśni</h1><p class="lead">Teksty, chwyty i domyślne formy w jednym miejscu.</p></div>
    <a class="button button-primary" href="<?= e(url('song-new')) ?>">+ Nowa pieśń</a>
</section>

<div class="toolbar">
    <label class="search-field"><span>⌕</span><input type="search" placeholder="Szukaj po tytule…" data-filter-list="#song-list"></label>
    <span class="muted"><?= count($songs) ?> pozycji</span>
</div>

<?php if (!$songs): ?>
    <div class="empty-state"><span class="empty-icon">♩</span><h2>Biblioteka jest pusta</h2><p>Dodaj pierwszą pieśń i podziel ją na części.</p></div>
<?php else: ?>
    <div class="table-card" id="song-list">
        <?php foreach ($songs as $song): ?>
            <a class="song-row" data-filter-text="<?= e(strtolower($song['title'] . ' ' . ($song['alt_title'] ?? ''))) ?>" href="<?= e(url('song-edit', ['id' => $song['id']])) ?>">
                <span class="song-key"><?= e($song['source_key'] ?: '—') ?></span>
                <span class="song-main"><strong><?= e($song['title']) ?></strong><small><?= (int) $song['section_count'] ?> części · <?= $song['bpm'] ? (int) $song['bpm'] . ' BPM' : 'tempo nieustalone' ?></small></span>
                <span class="notation-pill"><?= $song['notation_profile'] === 'intl' ? 'B/Bb · m' : 'H/B · małe' ?></span>
                <span class="row-arrow">→</span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
