<?php
if (user() && user()['role'] !== 'prospect') {
    redirect('index.php?page=dashboard');
}
$pageTitle = 'Đăng nhập nội bộ';
$demoAccounts = ['admin' => 'admin@cmc.edu.vn', 'student' => 'student@cmc.edu.vn', 'ambassador' => 'ambassador@cmc.edu.vn'];
$demoEmail = $demoAccounts[(string) ($_GET['demo'] ?? '')] ?? '';
?>
<main class="internal-login">
    <section class="internal-login-panel" aria-labelledby="loginTitle">
        <aside class="internal-login-visual">
            <img src="assets/img/cmc-connect-hero.png" alt="Minh họa cộng đồng sinh viên số CMC University">
            <div class="internal-login-overlay">
                <img class="login-logo" src="assets/img/cmc-university-horizontal.png" alt="CMC University">
                <div>
                    <p class="login-context">Student Connect</p>
                    <h2>Một hệ thống cho toàn bộ hành trình đại sứ.</h2>
                    <p>Nhận brief, sáng tạo nội dung, tư vấn P2P và theo dõi ghi nhận trong cùng một nơi.</p>
                </div>
                <div class="login-security"><i class="bi bi-shield-check"></i><span><strong>Không gian nội bộ</strong><small>Dữ liệu được phân quyền theo vai trò</small></span></div>
            </div>
        </aside>

        <div class="internal-login-form">
            <div class="login-mobile-brand"><img src="assets/img/cmc-university-horizontal.png" alt="CMC University"><span>Student Connect</span></div>
            <div class="login-form-head">
                <p class="login-context">Cổng vận hành nội bộ</p>
                <h1 id="loginTitle">Chào mừng trở lại</h1>
                <p>Đăng nhập bằng tài khoản CMC được cấp cho bạn.</p>
            </div>

            <form method="post" action="actions.php?action=login" class="login-form" novalidate>
                <?= csrf_field() ?>
                <div class="login-field">
                    <label class="form-label" for="loginEmail">Email CMC</label>
                    <div class="input-icon"><i class="bi bi-envelope"></i><input class="form-control" id="loginEmail" type="email" name="email" autocomplete="username" placeholder="name@cmc.edu.vn" value="<?= e($demoEmail) ?>" required></div>
                    <small>Dùng email quản trị, sinh viên hoặc đại sứ.</small>
                </div>
                <div class="login-field">
                    <div class="d-flex align-items-center justify-content-between"><label class="form-label" for="loginPassword">Mật khẩu</label><button class="password-toggle" type="button" data-password-toggle="loginPassword" aria-label="Hiện mật khẩu"><i class="bi bi-eye"></i> Hiện</button></div>
                    <div class="input-icon"><i class="bi bi-lock"></i><input class="form-control" id="loginPassword" type="password" name="password" autocomplete="current-password" placeholder="Nhập mật khẩu" value="<?= $demoEmail ? '123456' : '' ?>" required></div>
                </div>
                <button class="btn btn-brand btn-lg w-100 login-submit" type="submit">Đăng nhập <i class="bi bi-arrow-right"></i></button>
            </form>

            <div class="demo-accounts">
                <strong>Dùng nhanh tài khoản demo</strong>
                <small>Mật khẩu chung: 123456</small>
                <div><a href="index.php?page=login&demo=admin"><i class="bi bi-building"></i> Admin</a><a href="index.php?page=login&demo=student"><i class="bi bi-mortarboard"></i> Sinh viên</a><a href="index.php?page=login&demo=ambassador"><i class="bi bi-chat-square-text"></i> Đại sứ</a></div>
            </div>

            <p class="login-support"><i class="bi bi-life-preserver"></i> Cần hỗ trợ truy cập? Liên hệ Phòng Tuyển sinh CMC.</p>
        </div>
    </section>
</main>
