<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
require_auth(['admin','student','ambassador']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Chỉ hỗ trợ POST.');
}
verify_csrf();
$tab = is_string($_POST['tab'] ?? null) && in_array($_POST['tab'],['members','knowledge','quality','reports'],true) ? $_POST['tab'] : 'members';
try {
    AmbassadorProgram::handle($db,(int)user()['id'],is_string($_POST['action']??null)?$_POST['action']:'',$_POST);
    flash('success','Đã lưu thay đổi.');
} catch (InvalidArgumentException $error) {
    flash('warning',$error->getMessage());
} catch (Throwable $error) {
    error_log('Ambassador program: '.$error->getMessage());
    flash('danger','Chưa lưu được thay đổi. Vui lòng thử lại.');
}
redirect('index.php?page=ambassador-program&tab='.$tab);
