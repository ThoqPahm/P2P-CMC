<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Database.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec('PRAGMA foreign_keys = ON');

$migrate = new ReflectionMethod(Database::class, 'migrate');
$migrate->invoke(null, $db);

// Reproduce a cPanel database that already has users/campaigns but retained only one editorial item.
$db->exec('PRAGMA foreign_keys = OFF');
$careerId = (int) $db->query("SELECT id FROM submissions WHERE blog_title = 'Đi Career Fair khi còn là sinh viên: mình đã chuẩn bị gì?' LIMIT 1")->fetchColumn();
$authorId = (int) $db->query("SELECT user_id FROM submissions WHERE id = $careerId")->fetchColumn();
$db->exec("DELETE FROM submissions WHERE id <> $careerId");
$db->exec("DELETE FROM users WHERE role = 'ambassador' AND id <> $authorId");
$db->exec('PRAGMA foreign_keys = ON');

$migrate->invoke(null, $db);

$urls = [
    'https://www.tiktok.com/@ua.cmc/video/7675607526141873429',
    'https://www.tiktok.com/@ua.cmc/video/7674889049940823316',
    'https://cmcu.edu.vn/nganh-cong-nghe-thong-tin/',
    'https://cmcu.edu.vn/an-tuong-voi-ngay-hoi-thuc-tap-va-viec-lam-cmc-career-fair-2026-truong-dai-hoc-cmc-ket-noi-he-sinh-thai-doanh-nghiep-dong-hanh-kien-tao-nguon-nhan-luc-chat-luong-cao-cho-ky-nguyen-ai/',
    'https://cmcu.edu.vn/tu-giang-duong-cmcu-den-moi-truong-quoc-te-sinh-vien-truong-dai-hoc-cmc-san-sang-cho-hanh-trinh-trao-doi-hoc-tap-tai-trung-quoc/',
];
$quoted = implode(',', array_map([$db, 'quote'], $urls));
$count = (int) $db->query("SELECT COUNT(*) FROM submissions WHERE status = 'approved' AND content_url IN ($quoted)")->fetchColumn();
if ($count !== 5) throw new RuntimeException("Expected 5 deployable widget items, found $count.");

echo "PASS existing cPanel databases receive all 5 widget content fixtures.\n";
