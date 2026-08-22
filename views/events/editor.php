<?php use BandBook\View; $isEdit = !empty($event['id']); ?>
<section class="page-heading compact-heading"><div><a class="back-link" href="<?= e(url('events')) ?>">← Wydarzenia</a><p class="eyebrow"><?= $isEdit ? 'Ustawienia wydarzenia' : 'Nowe wydarzenie' ?></p><h1><?= e($event['name'] ?? 'Zaplanuj spotkanie') ?></h1></div></section>
<?php if (!empty($error)): ?><div class="form-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="narrow-form panel">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div class="form-grid">
        <label class="span-2">Nazwa wydarzenia<input name="name" required value="<?= e($event['name'] ?? '') ?>" placeholder="Np. Msza niedzielna — 10:30"></label>
        <label>Termin<input type="datetime-local" name="planned_at" value="<?= e(isset($event['planned_at']) && $event['planned_at'] ? date('Y-m-d\TH:i', strtotime($event['planned_at'])) : '') ?>"></label>
        <label>Miejsce<input name="location" value="<?= e($event['location'] ?? '') ?>" placeholder="Kościół / sala"></label>
        <label>Status<select name="status"><?php foreach (['draft','ready','live','finished','archived'] as $status): ?><option value="<?= e($status) ?>" <?= ($event['status'] ?? 'draft') === $status ? 'selected' : '' ?>><?= e(View::statusLabel($status)) ?></option><?php endforeach; ?></select></label>
        <label class="span-2">Komentarz do repertuaru<textarea name="comment" rows="4" placeholder="Informacje wspólne dla całego wydarzenia"><?= e($event['comment'] ?? '') ?></textarea></label>
    </div>
    <div class="form-actions"><a class="button button-ghost" href="<?= e($isEdit ? url('event', ['id' => $event['id']]) : url('events')) ?>">Anuluj</a><button class="button button-primary" type="submit"><?= $isEdit ? 'Zapisz zmiany' : 'Utwórz wydarzenie' ?></button></div>
</form>
