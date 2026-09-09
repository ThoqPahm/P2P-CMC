<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/PresentationMode.php';

function present_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "OK: $message\n";
}

$token = PresentationMode::issue(1);
$grant = PresentationMode::verify($token);
present_check(is_array($grant) && (int)$grant['admin_id'] === 1, 'signed presentation grant');
present_check(PresentationMode::verify($token . 'x') === null, 'reject modified grant');

$database = PresentationMode::bindDatabase((string)$grant['nonce']);
require_once __DIR__ . '/../app/Routes.php';
require_once __DIR__ . '/../app/ChatPrivacy.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/AmbassadorProfiles.php';
AmbassadorProfiles::migrate($database);
PresentationMode::seed($database);

present_check((string)$database->query('SELECT title FROM campaigns WHERE id=2')->fetchColumn() === 'Một ngày đi học ngành Digital Marketing', 'presentation campaign fixture');
present_check((int)$database->query("SELECT COUNT(*) FROM submissions WHERE blog_title LIKE 'Một ngày học Digital Marketing%'")->fetchColumn() === 1, 'published content fixture');
present_check((int)$database->query('SELECT COUNT(*) FROM messages WHERE conversation_id=1')->fetchColumn() === 3, 'conversation fixture');
present_check((string)$database->query('SELECT escalation_status FROM conversations WHERE id=1')->fetchColumn() === 'pending', 'pending handoff fixture');
present_check(PresentationMode::userId($database, 'admin') > 0 && PresentationMode::userId($database, 'ambassador') > 0, 'demo roles resolve');

PresentationMode::setStage($database, 'ambassador-handoff');
present_check((int)$database->query('SELECT is_escalated FROM conversations WHERE id=1')->fetchColumn() === 0, 'handoff starts before escalation');
PresentationMode::setStage($database, 'admin-review');
present_check((int)$database->query('SELECT is_escalated FROM conversations WHERE id=1')->fetchColumn() === 1, 'admin review receives escalation');
