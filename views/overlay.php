<?php $isGlobalOverlay = !empty($globalOverlay); ?>
<div class="overlay-app" data-audience-app data-projection-kind="<?= $isGlobalOverlay ? 'global-overlay' : 'overlay' ?>" data-revision="<?= (int) $snapshot['revision'] ?>" data-api="<?= e($isGlobalOverlay ? url('api-obs') : url('api-public', ['token' => $snapshot['event']['public_token']])) ?>">
    <main class="overlay-stage" data-audience-stage></main>
    <footer class="overlay-meta"><span data-audience-next></span><span class="connection online" data-connection><i></i> Połączono</span></footer>
</div>
<script type="application/json" data-audience-snapshot><?= json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
