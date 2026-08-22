<?php
$formPayload = array_map(fn ($item) => [
    'id' => (int) $item['id'], 'sectionId' => (int) $item['section_id'], 'label' => $item['section_label'],
    'transpose' => (int) $item['transpose_steps'], 'comment' => $item['comment'] ?? '',
], $eventSong['form']);
$sectionsPayload = array_map(fn ($section) => [
    'id' => (int) $section['id'], 'label' => $section['label'], 'type' => $section['type'],
], $eventSong['available_sections']);
?>
<section class="page-heading compact-heading"><div><a class="back-link" href="<?= e(url('event', ['id' => $eventSong['event_id']])) ?>">← <?= e($eventSong['event_name']) ?></a><p class="eyebrow">Pieśń w wydarzeniu</p><h1><?= e($eventSong['title']) ?></h1></div><a class="button button-primary" href="<?= e(url('live', ['id' => $eventSong['event_id']])) ?>">Podgląd live</a></section>
<?php if (!empty($error)): ?><div class="form-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="editor-layout" data-event-song-editor>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="form_json" data-event-form-json>
    <section class="editor-main">
        <article class="panel"><div class="panel-heading"><div><span class="step-number">1</span><h2>Ustawienia wykonania</h2></div></div>
            <div class="form-grid">
                <label>Transpozycja całej pieśni<div class="stepper"><button type="button" data-step="-1">−</button><input type="number" name="transpose_steps" value="<?= (int) $eventSong['transpose_steps'] ?>" min="-24" max="24"><button type="button" data-step="1">+</button></div></label>
                <label>Tempo dla wydarzenia<input type="number" name="bpm_override" min="20" max="300" value="<?= e($eventSong['bpm_override'] !== null ? (string) $eventSong['bpm_override'] : '') ?>" placeholder="Domyślne: <?= e((string) ($eventSong['default_bpm'] ?: '—')) ?>"></label>
                <label class="span-2">Komentarz do pieśni<textarea name="comment" rows="3" placeholder="Np. zaczynamy spokojnie, bez perkusji"><?= e($eventSong['comment'] ?? '') ?></textarea></label>
            </div>
        </article>
        <article class="panel"><div class="panel-heading"><div><span class="step-number">2</span><div><h2>Forma na to wydarzenie</h2><p>Kliknij część, aby dodać ją na końcu. Każde wystąpienie ma własną transpozycję i komentarz.</p></div></div></div><div class="event-form-palette" data-event-form-palette></div><div class="event-form-list" data-event-form-list></div></article>
    </section>
    <aside class="editor-sidebar"><div class="sticky-card"><p class="eyebrow">Wydarzenie</p><h3><?= e($eventSong['event_name']) ?></h3><p class="muted">Zmiany dotyczą tylko tej pozycji repertuaru.</p><button class="button button-primary button-wide" type="submit">Zapisz wykonanie</button><a class="button button-ghost button-wide" href="<?= e(url('event', ['id' => $eventSong['event_id']])) ?>">Anuluj</a></div></aside>
</form>
<script type="application/json" data-event-initial-form><?= json_encode($formPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<script type="application/json" data-event-sections><?= json_encode($sectionsPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
