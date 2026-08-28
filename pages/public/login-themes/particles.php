<main class="internal-login login-theme-particles">
    <section class="internal-login-panel" aria-labelledby="loginTitle">
        <aside class="internal-login-visual" data-cmc-particles aria-label="Biểu trưng CMC tạo từ các từ khóa của CMC University chuyển động theo con trỏ">
            <img class="login-particle-fallback" src="assets/img/cmc-university.svg" width="540" height="360" alt="">
            <canvas class="login-particle-canvas" id="cmcParticleCanvas" aria-hidden="true"></canvas>
            <div class="internal-login-overlay">
                <img class="login-logo" src="assets/img/cmc-university-horizontal.png" alt="CMC University">
            </div>
        </aside>

        <div class="internal-login-form">
            <div class="login-mobile-brand"><img src="assets/img/cmc-university-horizontal.png" alt="CMC University"></div>
            <div class="login-form-head"><h1 id="loginTitle">Đăng nhập</h1></div>
            <form method="post" action="actions.php?action=login" class="login-form" novalidate>
                <?= csrf_field() ?>
                <div class="login-field">
                    <label class="form-label" for="loginEmail">Email CMC</label>
                    <div class="input-icon"><i class="bi bi-envelope"></i><input class="form-control" id="loginEmail" type="email" name="email" autocomplete="username" placeholder="name@cmc.edu.vn" value="<?= e($demoEmail) ?>" required></div>
                </div>
                <div class="login-field">
                    <div class="login-field-head"><label class="form-label" for="loginPassword">Mật khẩu</label><button class="password-toggle" type="button" data-password-toggle="loginPassword" data-toggle-label="visible" aria-label="Hiện mật khẩu" title="Hiện mật khẩu"><i class="bi bi-eye" aria-hidden="true"></i><span>Hiện</span></button></div>
                    <div class="input-icon"><i class="bi bi-lock"></i><input class="form-control" id="loginPassword" type="password" name="password" autocomplete="current-password" placeholder="Nhập mật khẩu" value="<?= $demoEmail ? '123456' : '' ?>" required></div>
                </div>
                <button class="btn btn-brand btn-lg w-100 login-submit" type="submit">Đăng nhập <i class="bi bi-arrow-right"></i></button>
            </form>
            <div class="demo-accounts">
                <strong>Tài khoản demo</strong>
                <small>Mật khẩu: 123456</small>
                <div><a href="index.php?page=login&amp;demo=admin"><i class="bi bi-building"></i> Admin</a><a href="index.php?page=login&amp;demo=student"><i class="bi bi-mortarboard"></i> Sinh viên</a><a href="index.php?page=login&amp;demo=ambassador"><i class="bi bi-chat-square-text"></i> Đại sứ</a></div>
            </div>
        </div>
    </section>
</main>
