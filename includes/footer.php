<?php if (!$isPublic): ?>
        <?php if (!in_array($page ?? '', ['admin-dashboard','admin-campaigns','admin-moderation','ambassador-program'],true) && in_array($currentUser['role'],['admin','student','ambassador'],true)): ?>
        <div class="mt-4 pt-3 border-top"><a class="btn btn-outline-brand" href="index.php?page=ambassador-program"><i class="bi bi-signpost-split"></i> <?= $currentUser['role']==='admin'?'Vận hành đại sứ · Hồ sơ, nguồn tin & chất lượng':'Hành trình đại sứ · Định hướng & công việc' ?></a></div>
        <?php endif; ?>
        </div>
    </main>
<?php elseif (!$isLogin): ?>
    <footer class="public-footer">
        <div class="container">
            <div class="row g-4 align-items-center">
                <div class="col-lg-6"><a class="brand brand-light footer-brand" href="index.php?page=home"><img src="assets/img/cmc-university-horizontal.png" alt="CMC University"><span class="eambassador-wordmark"><span>e</span>Ambassador</span></a><p class="mt-3 mb-0">Kết nối người học bằng những câu chuyện thật.</p></div>
                <div class="col-lg-6 text-lg-end"><p class="mb-1">Hệ sinh thái đại sứ sinh viên số</p><small>Capstone Project | CMC University</small></div>
            </div>
        </div>
    </footer>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js?v=43"></script>
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
