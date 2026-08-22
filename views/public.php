<?php use BandBook\View; ?>
<div class="audience-app" data-audience-app data-revision="<?= (int) $snapshot['revision'] ?>" data-api="<?= e(url('api-public', ['token' => $snapshot['event']['public_token']])) ?>">
    <header class="audience-header"><span class="audience-brand"><span class="brand-mark">B</span> BandBook</span><div><strong><?= e($snapshot['event']['name']) ?></strong><span><?= e(View::eventDate($snapshot['event']['planned_at'])) ?></span></div><button type="button" data-fullscreen>Pełny ekran</button></header>
    <main class="audience-stage" data-audience-stage><div class="audience-wait"><span>♪</span><h1>Za chwilę zaczynamy</h1><p>Tekst pojawi się tutaj, gdy prowadzący wskaże pierwszą część.</p></div></main>
    <footer class="audience-footer"><span data-audience-next></span><span class="connection online" data-connection><i></i> Połączono</span></footer>
</div>
<script type="application/json" data-audience-snapshot><?= json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
