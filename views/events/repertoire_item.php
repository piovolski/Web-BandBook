<article class="repertoire-item" data-repertoire-item data-event-song-id="<?= (int) $song['id'] ?>">
    <span class="repertoire-number"><?= $index + 1 ?></span>
    <div class="repertoire-copy">
        <h3><a href="<?= e(url('event-song-edit', ['id' => $song['id']])) ?>"><?= e($song['title']) ?></a></h3>
        <p>Tonacja <?= e($song['source_key'] ?: '—') ?><?= (int) $song['transpose_steps'] !== 0 ? ' · transpozycja ' . ((int) $song['transpose_steps'] > 0 ? '+' : '') . (int) $song['transpose_steps'] : '' ?> · <?= $song['bpm_override'] ?: ($song['default_bpm'] ?: '—') ?> BPM</p>
    </div>
    <div class="repertoire-controls">
        <form method="post" action="<?= e(url('event-song-move', ['id' => $song['id'], 'direction' => -1])) ?>"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="icon-button" data-move-up title="Przenieś wyżej" <?= $index === 0 ? 'disabled' : '' ?>>↑</button></form>
        <form method="post" action="<?= e(url('event-song-move', ['id' => $song['id'], 'direction' => 1])) ?>"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="icon-button" data-move-down title="Przenieś niżej" <?= $index === $total - 1 ? 'disabled' : '' ?>>↓</button></form>
        <a class="button button-soft" href="<?= e(url('event-song-edit', ['id' => $song['id']])) ?>">Forma</a>
        <form method="post" action="<?= e(url('event-song-remove', ['id' => $song['id']])) ?>" onsubmit="return confirm('Usunąć tę pieśń z repertuaru?')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="icon-button danger" title="Usuń z repertuaru">×</button></form>
    </div>
</article>
