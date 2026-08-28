<main class="internal-login">
    <section class="internal-login-panel" aria-labelledby="loginTitle">
        <aside class="internal-login-visual" data-login-eyes aria-label="Các nhân vật CMC chuyển động theo con trỏ">
            <div class="internal-login-overlay">
                <img class="login-logo" src="assets/img/cmc-university-horizontal.png" alt="CMC University">
            </div>
            <div class="login-eye-scene" id="characters-scene" aria-hidden="true">
                <div class="eye-character" id="purple-character">
                    <img class="character-cmc-mark" src="assets/img/cmc-university.svg" alt="">
                    <div class="eye-face" id="purple-eyes">
                        <span class="eye-ball" id="purple-eye-1"><i class="eye-pupil" id="purple-pupil-1"></i></span>
                        <span class="eye-ball" id="purple-eye-2"><i class="eye-pupil" id="purple-pupil-2"></i></span>
                    </div>
                </div>
                <div class="eye-character" id="black-character">
                    <img class="character-cmc-mark" src="assets/img/cmc-university.svg" alt="">
                    <div class="eye-face" id="black-eyes">
                        <span class="eye-ball" id="black-eye-1"><i class="eye-pupil" id="black-pupil-1"></i></span>
                        <span class="eye-ball" id="black-eye-2"><i class="eye-pupil" id="black-pupil-2"></i></span>
                    </div>
                </div>
                <div class="eye-character" id="orange-character">
                    <img class="character-cmc-mark" src="assets/img/cmc-university.svg" alt="">
                    <div class="eye-face" id="orange-eyes">
                        <i class="eye-pupil" id="orange-pupil-1"></i>
                        <i class="eye-pupil" id="orange-pupil-2"></i>
                    </div>
                </div>
                <div class="eye-character" id="yellow-character">
                    <img class="character-cmc-mark" src="assets/img/cmc-university.svg" alt="">
                    <div class="eye-face" id="yellow-eyes">
                        <i class="eye-pupil" id="yellow-pupil-1"></i>
                        <i class="eye-pupil" id="yellow-pupil-2"></i>
                    </div>
                    <span class="eye-mouth" id="yellow-mouth"></span>
                </div>
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
                    <label class="form-label" for="loginPassword">Mật khẩu</label>
                    <div class="input-icon"><i class="bi bi-lock"></i><input class="form-control" id="loginPassword" type="password" name="password" autocomplete="current-password" placeholder="Nhập mật khẩu" value="<?= $demoEmail ? '123456' : '' ?>" required><button class="password-toggle" type="button" data-password-toggle="loginPassword" aria-label="Hiện mật khẩu" title="Hiện mật khẩu"><i class="bi bi-eye" aria-hidden="true"></i><span class="visually-hidden">Hiện mật khẩu</span></button></div>
                </div>
                <button class="btn btn-brand btn-lg w-100 login-submit" type="submit">Đăng nhập <i class="bi bi-arrow-right"></i></button>
            </form>
            <div class="demo-accounts">
                <strong>Tài khoản demo</strong>
                <small>Mật khẩu: 123456</small>
                <div><a href="index.php?page=login&demo=admin"><i class="bi bi-building"></i> Admin</a><a href="index.php?page=login&demo=student"><i class="bi bi-mortarboard"></i> Sinh viên</a><a href="index.php?page=login&demo=ambassador"><i class="bi bi-chat-square-text"></i> Đại sứ</a></div>
            </div>
        </div>
    </section>
</main>
