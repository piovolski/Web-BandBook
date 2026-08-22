<?php use BandBook\View; ?>
<section class="page-heading dashboard-heading">
    <div>
        <p class="eyebrow">Dzień dobry, <?= e(explode(' ', $user['display_name'])[0]) ?></p>
        <h1>Wszystko gotowe do grania?</h1>
        <p class="lead">Przygotuj repertuar, a podczas spotkania prowadź cały zespół z jednego miejsca.</p>
    </div>
    <a class="button button-primary" href="<?= e(url('event-new')) ?>">+ Nowe wydarzenie</a>
</section>

<section class="stats-grid" aria-label="Podsumowanie">
    <article class="stat-card"><span class="stat-value"><?= (int) $stats['songs'] ?></span><span class="stat-label">pieśni w bibliotece</span></article>
    <article class="stat-card"><span class="stat-value"><?= (int) $stats['events'] ?></span><span class="stat-label">wszystkich wydarzeń</span></article>
    <article class="stat-card accent"><span class="stat-value"><?= (int) $stats['ready'] ?></span><span class="stat-label">wydarzeń gotowych</span></article>
</section>

<section class="section-block">
    <div class="section-heading"><div><p class="eyebrow">Najbliższe</p><h2>Wydarzenia</h2></div><a href="<?= e(url('events')) ?>">Zobacz wszystkie →</a></div>
    <?php if (!$events): ?>
        <div class="empty-state"><span class="empty-icon">♪</span><h3>Zaplanuj pierwsze wydarzenie</h3><p>Dodaj pieśni, ułóż formę i otwórz wspólny widok live.</p></div>
    <?php else: ?>
        <div class="card-list">
            <?php foreach ($events as $event): ?>
                <article class="event-row">
                    <div class="date-tile"><strong><?= $event['planned_at'] ? e(date('d', strtotime($event['planned_at']))) : '—' ?></strong><span><?= $event['planned_at'] ? e(strtoupper(date('M', strtotime($event['planned_at'])))) : 'DATA' ?></span></div>
                    <div class="event-summary"><h3><a href="<?= e(url('event', ['id' => $event['id']])) ?>"><?= e($event['name']) ?></a></h3><p><?= e(View::eventDate($event['planned_at'])) ?><?= $event['location'] ? ' · ' . e($event['location']) : '' ?> · <?= (int) $event['song_count'] ?> pieśni</p></div>
                    <span class="status status-<?= e($event['status']) ?>"><?= e(View::statusLabel($event['status'])) ?></span>
                    <a class="button button-soft" href="<?= e(url('live', ['id' => $event['id']])) ?>">Otwórz live</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
