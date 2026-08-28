<?php if (!$isPublic): ?>
        </div>
    </main>
<?php elseif (!$isLogin): ?>
    <footer class="public-footer">
        <div class="container">
            <div class="row g-4 align-items-center">
                <div class="col-lg-6"><a class="brand brand-light footer-brand" href="index.php?page=home"><img src="assets/img/cmc-university-horizontal.png" alt="CMC University"><span>Student Connect</span></a><p class="mt-3 mb-0">Kết nối người học bằng những câu chuyện thật.</p></div>
                <div class="col-lg-6 text-lg-end"><p class="mb-1">Hệ sinh thái đại sứ sinh viên số</p><small>Capstone Project | CMC University</small></div>
            </div>
        </div>
    </footer>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js?v=41"></script>
<?php
$loginThemeScript = null;
if ($isLogin ?? false) {
    $loginThemeScript = login_theme_registry()[active_login_theme()]['script'] ?? null;
}
?>
<?php if ($loginThemeScript): ?>
<script defer src="<?= e($loginThemeScript) ?>"></script>
<?php endif; ?>
</body>
</html>
