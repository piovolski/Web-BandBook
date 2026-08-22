<?php use BandBook\View; ?>
<section class="page-heading">
    <div><p class="eyebrow">Planowanie</p><h1>Wydarzenia</h1><p class="lead">Repertuary przygotowane na próby, Msze i spotkania.</p></div>
    <a class="button button-primary" href="<?= e(url('event-new')) ?>">+ Nowe wydarzenie</a>
</section>
<?php if (!$events): ?>
    <div class="empty-state"><span class="empty-icon">◇</span><h2>Brak wydarzeń</h2><p>Utwórz pierwsze wydarzenie i dodaj do niego pieśni.</p></div>
<?php else: ?>
    <div class="events-grid">
        <?php foreach ($events as $event): ?>
            <article class="event-card">
                <div class="event-card-top"><span class="status status-<?= e($event['status']) ?>"><?= e(View::statusLabel($event['status'])) ?></span><span class="muted"><?= (int) $event['song_count'] ?> pieśni</span></div>
                <h2><a href="<?= e(url('event', ['id' => $event['id']])) ?>"><?= e($event['name']) ?></a></h2>
                <p class="event-meta"><strong><?= e(View::eventDate($event['planned_at'])) ?></strong><?= $event['location'] ? '<br>' . e($event['location']) : '' ?></p>
                <div class="event-actions"><a class="button button-soft" href="<?= e(url('event', ['id' => $event['id']])) ?>">Edytuj</a><a class="button button-primary" href="<?= e(url('live', ['id' => $event['id']])) ?>">Live →</a></div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
