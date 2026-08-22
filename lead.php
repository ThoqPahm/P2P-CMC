<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php?page=home');
}
verify_csrf();
$affiliateId = (int) ($_POST['affiliate_id'] ?? 0);
$fullName = trim((string) ($_POST['full_name'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
if (!$affiliateId || $fullName === '' || $phone === '') {
    flash('danger', 'Vui lòng nhập đủ thông tin bắt buộc.');
    redirect($_SERVER['HTTP_REFERER'] ?? 'index.php?page=home');
}
$db->beginTransaction();
$statement = $db->prepare('INSERT INTO leads (affiliate_id, full_name, phone, email, major) VALUES (?, ?, ?, ?, ?)');
$statement->execute([$affiliateId, $fullName, $phone, trim((string) ($_POST['email'] ?? '')), trim((string) ($_POST['major'] ?? ''))]);
$leadId = (int) $db->lastInsertId();
$statement = $db->prepare('UPDATE affiliate_links SET leads = leads + 1 WHERE id = ?');
$statement->execute([$affiliateId]);
$affiliate = rows('SELECT user_id FROM affiliate_links WHERE id = ?', [$affiliateId])[0] ?? null;
if ($affiliate) {
    $statement = $db->prepare("INSERT INTO wallet_transactions (user_id, type, points, description, reference_type, reference_id) VALUES (?, 'credit', 10, 'Lead mới từ link giới thiệu', 'lead', ?)");
    $statement->execute([$affiliate['user_id'], $leadId]);
}
$db->commit();
flash('success', 'Đăng ký thành công! Đội ngũ CMC sẽ sớm liên hệ với bạn.');
redirect('index.php?page=home');
