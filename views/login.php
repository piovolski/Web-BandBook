<section class="auth-card">
    <div class="brand brand-large"><span class="brand-mark">B</span><span>BandBook</span></div>
    <p class="eyebrow">Witaj ponownie</p>
    <h1>Zagrajmy razem</h1>
    <p class="muted">Zaloguj się, aby otworzyć repertuar zespołu.</p>
    <?php if (!empty($error)): ?><div class="form-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="stack-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label>Login<input name="username" required autocomplete="username" autofocus></label>
        <label>Hasło<input type="password" name="password" required autocomplete="current-password"></label>
        <button class="button button-primary button-wide" type="submit">Zaloguj się</button>
    </form>
</section>
