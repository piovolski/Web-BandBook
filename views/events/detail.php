<?php use BandBook\View; ?>
<section class="event-hero">
    <div>
        <a class="back-link" href="<?= e(url('events')) ?>">← Wydarzenia</a>
        <div class="event-title-line"><span class="status status-<?= e($event['status']) ?>"><?= e(View::statusLabel($event['status'])) ?></span><h1><?= e($event['name']) ?></h1></div>
        <p class="lead"><?= e(View::eventDate($event['planned_at'])) ?><?= $event['location'] ? ' · ' . e($event['location']) : '' ?></p>
    </div>
    <div class="button-group"><a class="button button-soft" href="<?= e(url('event-edit', ['id' => $event['id']])) ?>">Ustawienia</a><a class="button button-primary" href="<?= e(url('live', ['id' => $event['id']])) ?>">Otwórz widok live →</a></div>
</section>

<?php if ($event['comment']): ?><aside class="repertoire-note"><strong>Komentarz do wydarzenia</strong><p><?= nl2br(e($event['comment'])) ?></p></aside><?php endif; ?>

<div class="event-layout">
    <section>
        <div class="section-heading"><div><p class="eyebrow">Kolejność</p><h2>Repertuar</h2></div><span class="count-badge"><?= count($event['songs']) ?> pieśni</span></div>
        <?php if (!$event['songs']): ?>
            <div class="empty-state compact"><span class="empty-icon">+</span><h3>Dodaj pierwszą pieśń</h3><p>Wybierz ją z biblioteki po prawej stronie.</p></div>
        <?php else: ?>
            <div class="repertoire-list">
                <?php foreach ($event['songs'] as $index => $song): ?>
                    <article class="repertoire-item">
                        <span class="repertoire-number"><?= $index + 1 ?></span>
                        <div class="repertoire-copy"><h3><a href="<?= e(url('event-song-edit', ['id' => $song['id']])) ?>"><?= e($song['title']) ?></a></h3><p>Tonacja <?= e($song['source_key'] ?: '—') ?><?= (int) $song['transpose_steps'] !== 0 ? ' · transpozycja ' . ((int) $song['transpose_steps'] > 0 ? '+' : '') . (int) $song['transpose_steps'] : '' ?> · <?= $song['bpm_override'] ?: ($song['default_bpm'] ?: '—') ?> BPM</p></div>
                        <div class="repertoire-controls">
                            <form method="post" action="<?= e(url('event-song-move', ['id' => $song['id'], 'direction' => -1])) ?>"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="icon-button" title="Przenieś wyżej" <?= $index === 0 ? 'disabled' : '' ?>>↑</button></form>
                            <form method="post" action="<?= e(url('event-song-move', ['id' => $song['id'], 'direction' => 1])) ?>"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="icon-button" title="Przenieś niżej" <?= $index === count($event['songs']) - 1 ? 'disabled' : '' ?>>↓</button></form>
                            <a class="button button-soft" href="<?= e(url('event-song-edit', ['id' => $song['id']])) ?>">Forma</a>
                            <form method="post" action="<?= e(url('event-song-remove', ['id' => $song['id']])) ?>" onsubmit="return confirm('Usunąć tę pieśń z repertuaru?')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="icon-button danger" title="Usuń z repertuaru">×</button></form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <aside class="event-sidebar">
        <article class="panel add-song-card"><p class="eyebrow">Biblioteka</p><h2>Dodaj pieśń</h2>
            <?php if ($availableSongs): ?>
                <form method="post" action="<?= e(url('event-song-add', ['id' => $event['id']])) ?>" class="stack-form compact-form">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <label>Wybierz pieśń<select name="song_id"><?php foreach ($availableSongs as $song): ?><option value="<?= (int) $song['id'] ?>"><?= e($song['title']) ?></option><?php endforeach; ?></select></label>
                    <button class="button button-primary button-wide" type="submit">Dodaj do repertuaru</button>
                </form>
            <?php else: ?><p class="muted">Najpierw dodaj pieśń do biblioteki.</p><a class="button button-soft button-wide" href="<?= e(url('song-new')) ?>">Nowa pieśń</a><?php endif; ?>
        </article>

        <article class="panel share-card"><p class="eyebrow">Dla uczestników</p><h2>Publiczny widok</h2><p class="muted">Udostępnij tekst aktualnie granej części.</p><a class="button button-soft button-wide" target="_blank" rel="noopener" href="<?= e(url('public', ['token' => $event['public_token']])) ?>">Otwórz widok uczestnika ↗</a><button class="button button-ghost button-wide" type="button" data-copy-value="<?= e(url('public', ['token' => $event['public_token']])) ?>">Kopiuj adres</button><hr><p class="eyebrow">Transmisja</p><a class="button button-soft button-wide" target="_blank" rel="noopener" href="<?= e(url('overlay', ['token' => $event['public_token']])) ?>">Podgląd nakładki OBS ↗</a><button class="button button-ghost button-wide" type="button" data-copy-value="<?= e(url('overlay', ['token' => $event['public_token']])) ?>">Kopiuj adres OBS</button></article>
    </aside>
</div>
