<?php use BandBook\View; ?>
<div class="live-app" data-live-app data-event-id="<?= (int) $snapshot['event']['id'] ?>" data-revision="<?= (int) $snapshot['revision'] ?>" data-csrf="<?= e(csrf_token()) ?>" data-api="<?= e(url('api-live', ['id' => $snapshot['event']['id']])) ?>" data-action-api="<?= e(url('api-live-action', ['id' => $snapshot['event']['id']])) ?>" data-output-api="<?= e(url('api-live-output', ['id' => $snapshot['event']['id']])) ?>" data-setting-api="<?= e(url('api-live-setting', ['id' => $snapshot['event']['id']])) ?>" data-part-api="<?= e(url('api-live-part', ['id' => $snapshot['event']['id']])) ?>" data-song-edit-url="<?= e(url('song-edit')) ?>">
    <header class="live-header">
        <div><a class="back-link light" href="<?= e(url('event', ['id' => $snapshot['event']['id']])) ?>">← Repertuar</a><p class="eyebrow">Live · <?= e(View::eventDate($snapshot['event']['planned_at'])) ?></p><h1><?= e($snapshot['event']['name']) ?></h1></div>
        <div class="live-header-tools">
            <div class="live-output-switch" data-live-output-switch role="group" aria-label="Ekran uczestników">
                <button type="button" data-output-mode="blackout" title="Czarne tło (B)"><kbd>B</kbd> Blackout</button>
                <button type="button" data-output-mode="background" title="Zdjęcie bez tekstu (G)"><kbd>G</kbd> Tło</button>
                <button type="button" data-output-mode="text" title="Tekst na wybranym tle (T)"><kbd>T</kbd> Tekst</button>
            </div>
            <div class="live-font-control" data-live-font-control role="group" aria-label="Wielkość tekstu uczestników">
                <button type="button" data-font-scale-delta="-10" title="Zmniejsz tekst uczestników">A−</button>
                <output data-font-scale aria-live="polite"><?= (int) ($snapshot['state']['audience_font_scale'] ?? 100) ?>%</output>
                <button type="button" data-font-scale-delta="10" title="Powiększ tekst uczestników">A+</button>
            </div>
            <label class="follow-toggle"><input type="checkbox" checked data-follow-current> Śledź „teraz”</label><label class="live-notation">Chwyty<select data-live-notation><option value="pl" <?= $user['notation_profile'] === 'pl' ? 'selected' : '' ?>>H/B · małe</option><option value="intl" <?= $user['notation_profile'] === 'intl' ? 'selected' : '' ?>>B/Bb · m</option></select></label><span class="connection online" data-connection><i></i> Połączono</span>
        </div>
    </header>
    <div class="live-layout">
        <aside class="live-setlist"><p class="eyebrow">Repertuar</p><div data-live-song-nav></div><div class="live-event-note"><label>Komentarz wydarzenia<textarea rows="4" data-live-setting data-scope="event" data-id="<?= (int) $snapshot['event']['id'] ?>" data-field="comment"><?= e($snapshot['event']['comment'] ?? '') ?></textarea></label></div></aside>
        <main class="live-stage"><div data-live-stage></div></main>
    </div>
</div>
<script type="application/json" data-live-snapshot><?= json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
