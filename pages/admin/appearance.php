<?php
require_super_admin();
$pageTitle = 'Chọn giao diện đăng nhập';
$themes = login_theme_registry();
$activeTheme = active_login_theme();
?>
<section class="panel-card login-theme-admin">
    <header class="login-theme-head">
        <div>
            <h2>Giao diện đăng nhập</h2>
            <p>Chọn theme được hiển thị tại trang đăng nhập cho toàn hệ thống.</p>
        </div>
        <div class="d-flex flex-wrap gap-2"><a class="btn btn-light border" href="index.php?page=super-admin"><i class="bi bi-arrow-left"></i> Super Admin</a><a class="btn btn-light" href="index.php?page=login&amp;preview=1" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Xem trang login</a></div>
    </header>

    <form method="post" action="actions.php?action=set_login_theme">
        <?= csrf_field() ?>
        <div class="login-theme-grid">
            <?php foreach ($themes as $key => $theme): ?>
                <label class="login-theme-option">
                    <input type="radio" name="login_theme" value="<?= e($key) ?>" <?= $activeTheme === $key ? 'checked' : '' ?>>
                    <span class="login-theme-preview theme-preview-<?= e($key) ?>" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
                    <span class="login-theme-copy"><strong><?= e($theme['name']) ?></strong><small><?= e($theme['description']) ?></small></span>
                    <span class="login-theme-check" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
                </label>
            <?php endforeach; ?>

            <article class="login-theme-option is-placeholder" aria-disabled="true">
                <span class="login-theme-preview theme-preview-empty" aria-hidden="true"><i class="bi bi-plus-lg"></i></span>
                <span class="login-theme-copy"><strong>Theme tiếp theo</strong><small>Slot đã sẵn sàng để thêm giao diện login mới.</small></span>
            </article>
        </div>
        <footer class="login-theme-actions"><span>Đang dùng: <strong><?= e($themes[$activeTheme]['name']) ?></strong></span><button class="btn btn-brand" type="submit">Lưu lựa chọn</button></footer>
    </form>
</section>
