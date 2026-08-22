<?php
$isEdit = !empty($song['id']);
$sections = $song['sections'] ?? [];
$sectionPayload = [];
foreach ($sections as $section) {
    $sectionPayload[] = [
        'id' => (int) $section['id'], 'key' => 'id-' . $section['id'], 'type' => $section['type'],
        'label' => $section['label'], 'lyrics' => $section['lyrics'], 'chords' => $section['chords'], 'comment' => $section['comment'] ?? '',
    ];
}
$formPayload = [];
foreach (($song['form'] ?? []) as $item) {
    $formPayload[] = ['sectionKey' => 'id-' . $item['section_id'], 'transpose' => (int) $item['transpose_steps'], 'comment' => $item['comment'] ?? ''];
}
?>
<section class="page-heading compact-heading">
    <div><a class="back-link" href="<?= e(url('songs')) ?>">← Biblioteka</a><p class="eyebrow"><?= $isEdit ? 'Edycja pieśni' : 'Nowa pieśń' ?></p><h1><?= e($song['title'] ?? 'Bez tytułu') ?></h1></div>
    <?php if ($isEdit): ?>
        <form method="post" action="<?= e(url('song-archive', ['id' => $song['id']])) ?>" onsubmit="return confirm('Zarchiwizować tę pieśń?')">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="button button-danger-soft" type="submit">Archiwizuj</button>
        </form>
    <?php endif; ?>
</section>

<?php if (!empty($error)): ?><div class="form-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="editor-layout" data-song-editor>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="sections_json" data-sections-json>
    <input type="hidden" name="form_json" data-form-json>

    <section class="editor-main">
        <article class="panel">
            <div class="panel-heading"><div><span class="step-number">1</span><h2>Dane pieśni</h2></div><span class="save-indicator">Zmiany lokalne</span></div>
            <div class="form-grid">
                <label class="span-2">Tytuł<input name="title" required value="<?= e($song['title'] ?? '') ?>" placeholder="Np. Otwórz me oczy Panie"></label>
                <label>Tonacja źródłowa<input name="source_key" value="<?= e($song['source_key'] ?? '') ?>" placeholder="C"></label>
                <label>Tempo BPM<input type="number" min="20" max="300" name="bpm" value="<?= e(isset($song['bpm']) ? (string) $song['bpm'] : '') ?>" placeholder="72"></label>
                <label>Metrum<input name="meter" value="<?= e($song['meter'] ?? '') ?>" placeholder="4/4"></label>
                <label>Format zapisanych chwytów<select name="notation_profile"><option value="pl" <?= ($song['notation_profile'] ?? 'pl') === 'pl' ? 'selected' : '' ?>>H/B + małe molowe</option><option value="intl" <?= ($song['notation_profile'] ?? '') === 'intl' ? 'selected' : '' ?>>B/Bb + końcówka m</option></select></label>
                <label class="span-2">Komentarz<textarea name="comment" rows="2" placeholder="Opcjonalna informacja dla zespołu"><?= e($song['comment'] ?? '') ?></textarea></label>
            </div>
        </article>

        <article class="panel">
            <div class="panel-heading"><div><span class="step-number">2</span><div><h2>Części pieśni</h2><p>Każda linia tekstu odpowiada tej samej linii chwytów.</p></div></div><button class="button button-soft" type="button" data-add-section>+ Dodaj część</button></div>
            <div class="sections-editor" data-sections-container></div>
        </article>

        <article class="panel">
            <div class="panel-heading"><div><span class="step-number">3</span><div><h2>Forma domyślna</h2><p>Dodawaj części wielokrotnie i ustawiaj ich kolejność.</p></div></div></div>
            <div class="form-builder">
                <div class="form-palette" data-form-palette></div>
                <div class="form-sequence" data-form-sequence></div>
            </div>
        </article>
    </section>

    <aside class="editor-sidebar">
        <div class="sticky-card"><p class="eyebrow">Zapis</p><h3><?= $isEdit ? 'Aktualizujesz pieśń' : 'Tworzysz nową pieśń' ?></h3><p class="muted">Zmiany pieśni źródłowej będą dostępne w nowych repertuarach.</p><button class="button button-primary button-wide" type="submit">Zapisz pieśń</button><a class="button button-ghost button-wide" href="<?= e(url('songs')) ?>">Anuluj</a></div>
    </aside>
</form>

<script type="application/json" data-initial-sections><?= json_encode($sectionPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<script type="application/json" data-initial-form><?= json_encode($formPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
