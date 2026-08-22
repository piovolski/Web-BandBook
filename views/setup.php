<section class="auth-card">
    <div class="brand brand-large"><span class="brand-mark">B</span><span>BandBook</span></div>
    <p class="eyebrow">Pierwsze uruchomienie</p>
    <h1>Przygotuj śpiewnik zespołu</h1>
    <p class="muted">Utwórz konto administratora. Wszystkie dane pozostaną na Twoim serwerze.</p>
    <?php if (!empty($error)): ?><div class="form-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="stack-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label>Nazwa wyświetlana<input name="display_name" required autocomplete="name" value="<?= e($_POST['display_name'] ?? '') ?>"></label>
        <label>Login<input name="username" required autocomplete="username" value="<?= e($_POST['username'] ?? '') ?>"></label>
        <label>Hasło<input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
        <label>Domyślny zapis chwytów
            <select name="notation_profile">
                <option value="pl">H/B + małe litery molowe (fis, h)</option>
                <option value="intl">B/Bb + końcówka m (F#m, Bm)</option>
            </select>
        </label>
        <label class="check-row"><input type="checkbox" name="demo" value="1" checked> Dodaj pełny Śpiewnik guanelliański (257 pozycji)</label>
        <button class="button button-primary button-wide" type="submit">Utwórz BandBook</button>
    </form>
</section>
