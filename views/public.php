<div class="audience-app" data-audience-app data-projection-kind="audience" data-revision="<?= (int) $snapshot['revision'] ?>" data-api="<?= e(url('api-public', ['token' => $snapshot['event']['public_token']])) ?>" data-background-api="<?= e(url('event-background', ['token' => $snapshot['event']['public_token']])) ?>">
    <div class="audience-stage" data-audience-stage aria-live="polite"></div>
</div>
<script type="application/json" data-audience-snapshot><?= json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
